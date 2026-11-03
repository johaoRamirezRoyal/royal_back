<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Services\Cloudinary\ArchivosPrivados;
use App\Services\Cloudinary\CloudinaryUploadsAdapter;
use Illuminate\Http\Request;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentoController extends Controller
{
    /** Anchos permitidos en `?w=` (cada uno es una carpeta de caché; no se acepta cualquiera). */
    private const ANCHOS_MINIATURA = [64, 128, 256, 512];

    private const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Descarga o visualiza un documento del storage público.
     * Query params: directorio, nombre_documento, download (opcional).
     */
    public function descargar(Request $request)
    {
        $request->validate([
            'directorio' => 'required|string|max:500',
            'nombre_documento' => 'required|string|max:500',
            'download' => 'nullable|boolean',
        ]);

        $directorio = trim($request->input('directorio'), '/');
        $nombreDocumento = $request->input('nombre_documento');

        if (str_contains($directorio, '..') || str_contains($nombreDocumento, '..')) {
            return $this->error('Ruta no válida.', 400);
        }

        $ruta = $directorio . '/' . $nombreDocumento;
        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($ruta)) {
            return $this->error('Documento no encontrado.', 404);
        }

        if ($request->boolean('download')) {
            return $disk->download($ruta);
        }

        return $disk->response($ruta);
    }

    /**
     * Documento privado que llegó en un JSON (ArchivosPrivados::paraCliente): url_archivo de
     * Admisiones, firmas... Requiere auth:api. `{nombre}` solo da la extensión al frontend.
     */
    public function privado(Request $request, string $token)
    {
        $recurso = ArchivosPrivados::deToken($token);
        if (!$recurso) {
            abort(404);
        }

        [$tipo, $archivo] = $recurso;

        return $this->transmitir(ArchivosPrivados::firmada($tipo, $archivo), basename($archivo), $request->boolean('descargar'));
    }

    /**
     * Pasa por Laravel un archivo privado de Cloudinary (su URL firmada nunca llega al
     * navegador), sin caché: al cerrar sesión deja de abrir.
     */
    private function transmitir(string $url, string $nombre, bool $descargar)
    {
        $origen = Http::withOptions(['stream' => true])->timeout(30)->get($url);
        if (!$origen->successful()) {
            abort($origen->status() === 404 ? 404 : 502);
        }

        $tipo = $origen->header('Content-Type');
        if ($tipo === '' || str_starts_with($tipo, 'application/octet-stream')) {
            // Los `raw` (Word, Excel…) llegan como octet-stream: el tipo sale de la extensión.
            $tipo = (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($nombre) ?? 'application/octet-stream';
        }
        $cuerpo = $origen->toPsrResponse()->getBody();

        return response()->stream(function () use ($cuerpo) {
            while (!$cuerpo->eof()) {
                echo $cuerpo->read(65536);
            }
        }, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => HeaderUtils::makeDisposition($descargar ? 'attachment' : 'inline', $nombre, str_replace('%', '', Str::ascii($nombre))),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sirve cualquier archivo del disco de uploads (imagen, pdf, etc.).
     * Requiere auth:api. La ruta puede incluir subdirectorios: /api/public/{directorio/archivo}
     * `?descargar=1` lo entrega como descarga en vez de verlo.
     */
    public function verPublico(Request $request, string $ruta)
    {
        if (str_contains($ruta, '..')) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        // `private` (no `public`): esta ruta va detrás de auth:api, no debe cachearse en
        // proxies/CDNs compartidos, solo en el navegador del propio usuario. Fotos de
        // carnet y demás archivos acá casi nunca cambian una vez subidos — evita que se
        // vuelvan a descargar completas cada vez que se abre el mismo curso/roster.
        $cache = ['Cache-Control' => 'private, max-age=604800'];
        $ancho = $request->integer('w');

        $adapter = $disk->getAdapter();
        if ($adapter instanceof CloudinaryUploadsAdapter) {
            $limpia = ltrim(str_replace('\\', '/', $ruta), '/');
            $esImagen = in_array(strtolower(pathinfo($limpia, PATHINFO_EXTENSION)), self::EXTENSIONES_IMAGEN, true);
            $miniatura = $esImagen && in_array($ancho, self::ANCHOS_MINIATURA, true) ? "w_{$ancho},c_limit,f_auto,q_auto" : null;
            [$id, , , $entrega] = $adapter->recurso($limpia);

            // Documento privado: pasa por Laravel, así solo lo abre quien tiene sesión en ese
            // momento (una URL firmada no vence). Las fotos (de carnet, y las imágenes de la raíz:
            // perfil y carnets de SAMI) van firmadas al CDN: en una lista serían decenas por Laravel.
            if ($entrega === 'authenticated' && !ArchivosPrivados::esFoto($id) && !($esImagen && !str_contains($limpia, '/'))) {
                return $this->transmitir($adapter->recurso($limpia, $miniatura)[2], basename($limpia), $request->boolean('descargar'));
            }

            // Redirección a la URL firmada: Cloudinary hace la miniatura y el archivo no pasa por
            // Laravel (el 404 lo da Cloudinary). Sin caché: guardado, el 302 seguía abriendo la
            // foto después de cerrar sesión.
            $transformacion = implode('/', array_filter([$miniatura, $request->boolean('descargar') ? 'fl_attachment' : null]));

            return redirect()->away($adapter->recurso($limpia, $transformacion ?: null)[2], 302, ['Cache-Control' => 'no-store']);
        }

        if (!$disk->exists($ruta)) {
            abort(404);
        }

        if ($request->boolean('descargar')) {
            return $disk->download($ruta);
        }

        // `?w=128`: miniatura de ese ancho para avatares y listas, en vez del original
        // (una foto de celular pesa MB y se muestra en 36px). Si no es una imagen o no se
        // puede leer, se sirve el original.
        if (in_array($ancho, self::ANCHOS_MINIATURA, true) && ($miniatura = $this->miniatura($disk, $ruta, $ancho))) {
            // response()->file marca la respuesta como `public`: se vuelve a `private` (ver arriba).
            return response()->file($miniatura, $cache)->setPrivate();
        }

        return $disk->response($ruta, null, $cache);
    }

    /**
     * Ruta local de la miniatura de `$ancho` px (WebP, o JPEG si GD no tiene WebP). Se genera
     * la primera vez y se reusa mientras el original no cambie. null = se sirve el original.
     */
    private function miniatura(Filesystem $disk, string $ruta, int $ancho): ?string
    {
        if (!in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), self::EXTENSIONES_IMAGEN, true)) {
            return null;
        }

        $webp = function_exists('imagewebp');
        $destino = storage_path("app/miniaturas/{$ancho}/" . sha1($ruta) . ($webp ? '.webp' : '.jpg'));
        if (is_file($destino) && filemtime($destino) >= $disk->lastModified($ruta)) {
            return $destino;
        }

        $contenido = $disk->get($ruta);
        $imagen = $contenido ? @imagecreatefromstring($contenido) : false;
        if (!$imagen) {
            return null;
        }

        // GD ignora la orientación EXIF que sí respeta el navegador: sin esto, las fotos de
        // celular salen acostadas.
        $orientacion = function_exists('exif_read_data')
            ? (@exif_read_data('data://image/jpeg;base64,' . base64_encode($contenido))['Orientation'] ?? 1)
            : 1;
        $imagen = match ((int) $orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        if (imagesx($imagen) > $ancho) {
            $imagen = imagescale($imagen, $ancho);
        }
        imagesavealpha($imagen, true);

        // Se escribe aparte y se renombra: dos peticiones a la vez nunca sirven un archivo a medias.
        @mkdir(dirname($destino), 0775, true);
        $temporal = $destino . '.' . uniqid('', true);
        $ok = $webp ? imagewebp($imagen, $temporal, 80) : imagejpeg($imagen, $temporal, 82);
        imagedestroy($imagen);

        return $ok && rename($temporal, $destino) ? $destino : null;
    }
}

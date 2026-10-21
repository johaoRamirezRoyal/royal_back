<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

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
     * Sirve cualquier archivo del disco de uploads (imagen, pdf, etc.).
     * Requiere auth:api. La ruta puede incluir subdirectorios: /api/public/{directorio/archivo}
     */
    public function verPublico(Request $request, string $ruta)
    {
        if (str_contains($ruta, '..')) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($ruta)) {
            abort(404);
        }

        // `private` (no `public`): esta ruta va detrás de auth:api, no debe cachearse en
        // proxies/CDNs compartidos, solo en el navegador del propio usuario. Fotos de
        // carnet y demás archivos acá casi nunca cambian una vez subidos — evita que se
        // vuelvan a descargar completas cada vez que se abre el mismo curso/roster.
        $cache = ['Cache-Control' => 'private, max-age=604800'];

        // `?w=128`: miniatura de ese ancho para avatares y listas, en vez del original
        // (una foto de celular pesa MB y se muestra en 36px). Si no es una imagen o no se
        // puede leer, se sirve el original.
        $ancho = $request->integer('w');
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

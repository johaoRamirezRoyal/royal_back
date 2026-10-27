<?php

namespace App\Services\Cloudinary;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Throwable;

/**
 * Disco de uploads en Cloudinary (driver `cloudinary_uploads`, disco `cloudinary`). La ruta
 * es la misma que se guarda en la BD ("biblioteca/x.jpg", o "x.pdf" en registros viejos) y
 * vive en `<prefijo>/<ruta>`, igual que la dejó POST /api/migracion/cloudinary: así los
 * archivos migrados y los nuevos se direccionan igual, y el frontend arma la URL sin
 * preguntarle al backend (src/utils/fileUrl.ts, misma regla).
 *
 * - Imágenes y PDF van como `image` (public_id sin extensión); el resto como `raw` (public_id
 *   con extensión, que es como lo deja Cloudinary).
 * - El nombre se sanea como CloudinaryService::uploadFile: fuera de [A-Za-z0-9_-] pasa a "_".
 * - Leer y comprobar existencia usa la URL de entrega (CDN), no la Admin API: esa tiene
 *   cuota por hora y url()/exists() se llaman por fila en los listados.
 */
class CloudinaryUploadsAdapter implements FilesystemAdapter
{
    private const IMAGENES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'pdf'];

    public function __construct(private Cloudinary $cloudinary, private string $prefijo) {}

    /** @return array{0: string, 1: string, 2: string} [public_id, resource_type, url] */
    public function recurso(string $ruta): array
    {
        $ruta = ltrim(str_replace('\\', '/', $ruta), '/');
        $carpeta = str_contains($ruta, '/') ? preg_replace('/[^A-Za-z0-9_\-\/]/', '', dirname($ruta)) : '';
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $base = preg_replace('/[^A-Za-z0-9\-_]/', '_', pathinfo($ruta, PATHINFO_FILENAME));
        $tipo = in_array($ext, self::IMAGENES, true) ? 'image' : 'raw';

        $id = implode('/', array_filter([$this->prefijo, $carpeta, $base], fn ($p) => $p !== ''));
        if ($tipo === 'raw' && $ext !== '') {
            $id .= ".{$ext}";
        }

        $cloud = $this->cloudinary->configuration->cloud->cloudName;
        $url = "https://res.cloudinary.com/{$cloud}/{$tipo}/upload/{$id}".($tipo === 'image' && $ext !== '' ? ".{$ext}" : '');

        return [$id, $tipo, $url];
    }

    /** Lo llama Storage::url(). */
    public function getUrl(string $path): string
    {
        return $this->recurso($path)[2];
    }

    public function fileExists(string $path): bool
    {
        try {
            return Http::timeout(10)->head($this->getUrl($path))->successful();
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        // Las carpetas son implícitas en Cloudinary. false, no true: exists() es
        // fileExists() || directoryExists(), y con true todo archivo "existiría".
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->subir($path, 'data:application/octet-stream;base64,'.base64_encode($contents));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->subir($path, $contents);
    }

    private function subir(string $path, mixed $archivo): void
    {
        [$id, $tipo] = $this->recurso($path);

        try {
            $this->cloudinary->uploadApi()->upload($archivo, [
                'public_id' => $id,
                'resource_type' => $tipo,
                'overwrite' => true,
                'invalidate' => true,
            ]);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        $respuesta = Http::timeout(30)->get($this->getUrl($path));
        if (!$respuesta->successful()) {
            throw UnableToReadFile::fromLocation($path, "HTTP {$respuesta->status()}");
        }

        return $respuesta->body();
    }

    public function readStream(string $path)
    {
        $stream = @fopen($this->getUrl($path), 'rb');
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        [$id, $tipo] = $this->recurso($path);

        try {
            $resultado = $this->cloudinary->uploadApi()->destroy($id, ['resource_type' => $tipo, 'invalidate' => true]);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }

        if (!in_array($resultado['result'] ?? null, ['ok', 'not found'], true)) {
            throw UnableToDeleteFile::atLocation($path, (string) ($resultado['result'] ?? 'respuesta desconocida'));
        }
    }

    public function deleteDirectory(string $path): void
    {
        throw UnableToDeleteFile::atLocation($path, 'No se borran carpetas enteras en Cloudinary.');
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Carpetas implícitas: se crean al subir.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Cloudinary no maneja visibilidad.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $mime = (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($path);
        if (!$mime) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return new FileAttributes($path, null, null, null, $mime);
    }

    public function lastModified(string $path): FileAttributes
    {
        $cabecera = $this->cabeceras($path)->header('Last-Modified');

        return new FileAttributes($path, null, null, $cabecera ? strtotime($cabecera) : null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $cabecera = $this->cabeceras($path)->header('Content-Length');

        return new FileAttributes($path, $cabecera !== '' ? (int) $cabecera : null);
    }

    private function cabeceras(string $path): \Illuminate\Http\Client\Response
    {
        $respuesta = Http::timeout(10)->head($this->getUrl($path));
        if (!$respuesta->successful()) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', "HTTP {$respuesta->status()}");
        }

        return $respuesta;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return []; // ponytail: nadie lista el disco de uploads; si hace falta, Admin API (con cuota)
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // Cloudinary sube directo desde la URL de origen: no pasa el archivo por el servidor.
        $this->subir($destination, $this->getUrl($source));
    }
}

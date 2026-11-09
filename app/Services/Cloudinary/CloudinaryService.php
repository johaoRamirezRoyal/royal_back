<?php

namespace App\Services\Cloudinary;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\AdminManagement\BasesDatosService;

class CloudinaryService
{
    protected UploadApi $uploadApi;

    public function __construct()
    {
        /**
         * Configuración Cloudinary
         */
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->uploadApi = new UploadApi;
    }

    /**
     * Subir archivo a Cloudinary
     *
     * @return array{
     *     error: bool,
     *     message: string,
     *     data: array
     * }
     */
    /**
     * `<carpeta raíz del colegio de la sesión>/<subcarpeta>`, para las subidas de datos de un
     * colegio. Lo transversal (banners de administración, logos de marcas) no la usa.
     */
    public static function carpetaColegio(string $subcarpeta): string
    {
        return BasesDatosService::carpeta(config('database.default')) . '/' . trim($subcarpeta, '/');
    }

    public function uploadFile(
        UploadedFile $file,
        string $folder = 'uploads',
        // Opcional: por defecto el public_id es el nombre original del archivo (con
        // overwrite), así que dos archivos con el mismo nombre se pisan entre sí. Pasar
        // un nombre único cuando eso no es aceptable (ej. revistas de Noticias).
        ?string $publicIdPersonalizado = null,
        // Opcional: reemplaza la lista de extensiones por defecto (ver validateFile).
        ?array $extensionesPermitidas = null
    ): array {
        try {
            $validation = $this->validateFile($file, $extensionesPermitidas);

            if ($validation['error']) {
                return [
                    'error' => true,
                    'message' => $validation['message'],
                    'data' => [],
                ];
            }

            $folder = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $folder);
            $resourceType = $this->getResourceType($file);

            // Obtener nombre y extensión
            $originalNameComplete = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'tmp' || empty($extension)) {
                $extension = strtolower(pathinfo($originalNameComplete, PATHINFO_EXTENSION));
            }

            if (empty($extension) || $extension === 'tmp') {
                $extension = 'pdf';
            }

            $originalName = pathinfo($originalNameComplete, PATHINFO_FILENAME);
            $originalName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $originalName);

            // Sin extensión en el public_id, también en raw: Cloudinary la agrega.
            $publicId = $originalName;

            if ($publicIdPersonalizado) {
                $publicId = preg_replace('/[^A-Za-z0-9\-_]/', '_', $publicIdPersonalizado);
            }

            if (! self::usaCloudinary()) {
                return $this->guardarLocal($file, $folder, $publicId, $extension, $resourceType);
            }

            $options = [
                'folder' => $folder,
                'resource_type' => $resourceType,
                'use_filename' => true,
                'unique_filename' => false,
                'overwrite' => true,
                'public_id' => $publicId,
                // `authenticated` en las carpetas privadas (ArchivosPrivados): sin firma, 401.
                'type' => ArchivosPrivados::tipoEntrega("{$folder}/{$publicId}"),
                // Para PDFs específicamente
                'format' => $extension,
            ];

            $response = $this->uploadApi->upload(
                $file->getRealPath(),
                $options
            );

            Log::alert('Cloudinary Upload Response',
                [
                    'response' => $response,
                    'options' => $options,
                ]
            );

            if (! $response) {
                throw new \Exception('Cloudinary devolvió una respuesta vacía.');
            }

            // Obtener el public_id real de Cloudinary (puede tener .tmp)
            $returnedPublicId = $response['public_id'] ?? '';

            // Limpiar .tmp si existe
            $cleanPublicId = preg_replace('/\.tmp$/i', '', $returnedPublicId);

            return [
                'error' => false,
                'message' => 'Archivo subido correctamente.',
                'data' => [
                    'public_id' => $cleanPublicId,
                    'public_id_with_extension' => $cleanPublicId.'.'.$extension,
                    'url' => $response['secure_url'],
                    'url_with_extension' => str_replace(
                        '/'.$returnedPublicId,
                        '/'.$cleanPublicId.'.'.$extension,
                        $response['secure_url']
                    ),
                    'format' => $extension,
                    'size' => $response['bytes'] ?? null,
                    'resource_type' => $response['resource_type'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudinary Upload Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'error' => true,
                'message' => 'Error al subir archivo: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Obtener URL de un archivo por su public_id
     */
    public function getFileUrl(string $publicId, string $format = ''): array
    {
        try {
            if (empty($publicId)) {
                return [
                    'error' => true,
                    'message' => 'El public_id no puede estar vacío.',
                    'data' => [],
                ];
            }

            $images = ['png', 'jpg', 'jpeg', 'webp'];
            if (in_array($format, $images)) {
                $resourceType = 'image';
            } else {
                $resourceType = 'raw';
            }

            $cloudName = config('cloudinary.cloud_name');
            $url = "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$publicId}.{$format}";

            return [
                'error' => false,
                'message' => 'URL generada correctamente.',
                'data' => [
                    'url' => $url,
                    'public_id' => $publicId,
                    'format' => $format,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudinary GetUrl Error', [
                'message' => $e->getMessage(),
                'public_id' => $publicId,
                'format' => $format,
            ]);

            return [
                'error' => true,
                'message' => 'Error al generar la URL: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Descarga a un temporal local un archivo fijo de royal/royal-school (ej.
     * 'logotipoBackground.png', 'paz-salvo/sello.webp'): TCPDF/PhpSpreadsheet necesitan
     * ruta en disco, no una URL. Null si la descarga falla: el documento sale sin esa imagen.
     */
    public function rutaLocalRoyalSchool(string $archivo): ?string
    {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        $publicId = 'royal/royal-school/'.substr($archivo, 0, -strlen($extension) - 1);
        $url = $this->getFileUrl($publicId, $extension)['data']['url'] ?? null;
        // Las firmas de royal-school son privadas (ArchivosPrivados): sin firmar la URL, 401.
        $url = $url ? ArchivosPrivados::firmar($url) : null;

        try {
            // ponytail: sin caché, una descarga por documento; cachear en disco si pesa en lotes.
            $respuesta = $url ? Http::timeout(10)->get($url) : null;
        } catch (\Throwable $e) {
            $respuesta = null;
        }

        if (! $respuesta?->successful()) {
            Log::warning('No se pudo descargar el archivo de royal-school desde Cloudinary', ['archivo' => $archivo, 'url' => $url]);

            return null;
        }

        $temporal = tempnam(sys_get_temp_dir(), 'royal').'.'.$extension;
        file_put_contents($temporal, $respuesta->body());

        return $temporal;
    }

    /**
     * Si este entorno escribe en Cloudinary. El disco local SOLO se usa en `local` y `testing`:
     * cualquier otro entorno (producción) va siempre a Cloudinary, sin importar UPLOADS_DISK, para
     * que un .env mal puesto nunca deje los archivos de producción en el disco del servidor. En
     * local las credenciales del .env son las reales: sin esta regla una prueba subía —o
     * borraba— en la nube de producción.
     */
    public static function usaCloudinary(): bool
    {
        return ! app()->environment(['local', 'testing']);
    }

    /**
     * uploadFile en local: guarda en el disco `public` y responde con la misma forma.
     * La URL es la de GET /api/public/{ruta} (con sesión), no la de /storage: así lo privado
     * (firmas, documentos) tampoco queda abierto en local.
     */
    private function guardarLocal(UploadedFile $file, string $folder, string $publicId, string $extension, string $resourceType): array
    {
        $ruta = $file->storeAs($folder, "{$publicId}.{$extension}", 'public');
        if (! $ruta) {
            throw new \RuntimeException('No se pudo guardar el archivo en el disco local.');
        }

        $url = url('/api/public/' . implode('/', array_map('rawurlencode', explode('/', $ruta))));

        return [
            'error' => false,
            'message' => 'Archivo guardado en el disco local (fuera de producción no se sube a Cloudinary).',
            'data' => [
                'public_id' => "{$folder}/{$publicId}",
                'public_id_with_extension' => $ruta,
                'url' => $url,
                'url_with_extension' => $url,
                'format' => $extension,
                'size' => $file->getSize(),
                'resource_type' => $resourceType,
            ],
        ];
    }

    /** deleteFile fuera de producción: borra el archivo del disco local; nunca toca Cloudinary. */
    private function borrarLocal(string $publicId): array
    {
        $publicId = ltrim($publicId, '/');
        if (str_contains($publicId, '..')) {
            return ['error' => true, 'message' => 'public_id no válido.'];
        }

        $disco = Storage::disk('public');
        $base = basename($publicId);
        // Con o sin extensión: los public_id de imagen no la llevan.
        $archivos = array_filter(
            $disco->files(dirname($publicId) === '.' ? '' : dirname($publicId)),
            fn (string $f) => basename($f) === $base || str_starts_with(basename($f), "{$base}.")
        );
        $disco->delete(array_values($archivos));

        return [
            'error' => false,
            'message' => $archivos ? 'Archivo local eliminado.' : 'Fuera de producción no se borra en Cloudinary; no había archivo local.',
            'data' => ['public_id' => $publicId],
        ];
    }

    /**
     * Elimina un archivo privado a partir de la URL que guarda la BD (las firmas no guardan el
     * public_id). Error si la URL no es de un archivo privado de esta nube.
     */
    public function deleteFileByUrl(string $url): array
    {
        if (! self::usaCloudinary()) {
            // Fuera de producción solo se borra lo que guardarLocal subió (/api/public/...); una URL
            // de Cloudinary (datos copiados de producción) se deja tal cual.
            $ruta = parse_url($url, PHP_URL_PATH);
            if (is_string($ruta) && str_starts_with($ruta, '/api/public/')) {
                return $this->borrarLocal(rawurldecode(substr($ruta, strlen('/api/public/'))));
            }

            return ['error' => false, 'message' => 'Fuera de producción no se borra en Cloudinary.'];
        }

        $recurso = ArchivosPrivados::recursoDe($url);
        if (! $recurso) {
            return ['error' => true, 'message' => "URL no reconocida, no se borra: {$url}"];
        }

        [$tipo, , $publicId] = $recurso;

        return $this->deleteFile($publicId, $tipo);
    }

    /**
     * Eliminar archivo de Cloudinary
     */
    public function deleteFile(
        string $publicId,
        string $resourceType = 'raw'
    ): array {
        try {

            if (empty($publicId)) {
                return [
                    'error' => true,
                    'message' => 'El public_id no puede estar vacío.',
                ];
            }

            if (! self::usaCloudinary()) {
                return $this->borrarLocal($publicId);
            }

            $result = $this->uploadApi->destroy(
                $publicId,
                [
                    'resource_type' => $resourceType,
                    'type' => ArchivosPrivados::tipoEntrega($publicId),
                    'invalidate' => true,
                ]
            );

            Log::info('Cloudinary Destroy Response', [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'result' => $result,
            ]);

            $apiResult = $result['result'] ?? null;

            if ($apiResult === 'ok' || $apiResult === 'not found') {
                return [
                    'error' => false,
                    'message' => $apiResult === 'not found'
                        ? 'El archivo no existía en Cloudinary, se omite.'
                        : 'Archivo eliminado correctamente.',
                    'data' => [
                        'public_id' => $publicId,
                        'cloudinary_result' => $apiResult,
                    ],
                ];
            }

            return [
                'error' => true,
                'message' => 'Error al eliminar archivo: '.($apiResult ?? 'respuesta desconocida'),
            ];
        } catch (\Throwable $e) {

            Log::error(
                'Cloudinary Delete Error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'public_id' => $publicId,
                    'resource_type' => $resourceType,
                ]
            );

            return [
                'error' => true,
                'message' => 'Error al eliminar archivo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Obtener tipo de recurso
     */
    private function getResourceType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if (str_contains($mime, 'image')) {
            return 'image';
        }

        if (str_contains($mime, 'video')) {
            return 'video';
        }

        /**
         * PDFs y Documentos (CORREGIDO)
         * Se cambia 'auto' por 'raw' para obligar a Cloudinary a mantenerlo como binario
         */
        if ($extension === 'pdf') {
            return 'image';
        }

        if (
            in_array(
                $extension,
                ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']
            )
        ) {
            return 'raw';
        }

        return 'raw';
    }

    /**
     * Validar archivo
     */
    private function validateFile(UploadedFile $file, ?array $allowedExtensions = null): array
    {
        $maxSize = 10 * 1024 * 1024;

        if ($file->getSize() > $maxSize) {
            return [
                'error' => true,
                'message' => 'El archivo excede 10MB.',
            ];
        }

        $allowedExtensions ??= ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions)) {
            return [
                'error' => true,
                'message' => 'Tipo de archivo no permitido.',
            ];
        }

        return [
            'error' => false,
            'message' => 'Archivo válido.',
        ];
    }
}

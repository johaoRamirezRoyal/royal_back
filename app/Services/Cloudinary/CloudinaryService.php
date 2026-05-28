<?php

namespace App\Services\Cloudinary;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

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
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
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
    public function uploadFile(
        UploadedFile $file,
        string $folder = 'uploads'
    ): array {
        try {
            $validation = $this->validateFile($file);

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

            // SOLUCIÓN: Para RAW files, NO incluir extensión en public_id
            // Cloudinary la añade automáticamente basado en el Content-Type
            if ($resourceType === 'raw') {
                $publicId = $originalName;
            } else {
                $publicId = $originalName;
            }

            $options = [
                'folder' => $folder,
                'resource_type' => $resourceType,
                'use_filename' => true,
                'unique_filename' => false,
                'overwrite' => true,
                'public_id' => $publicId,
                // IMPORTANTE: Forzar el tipo MIME correcto
                'type' => 'upload',
                // Para PDFs específicamente
                'format' => $extension,
            ];

            $response = $this->uploadApi->upload(
                $file->getRealPath(),
                $options
            );

            log::alert('Cloudinary Upload Response',
                [
                    'response' => $response,
                    'options' => $options,
                ]
            );

            if (!$response) {
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
                    'public_id_with_extension' => $cleanPublicId . '.' . $extension,
                    'url' => $response['secure_url'],
                    'url_with_extension' => str_replace(
                        '/' . $returnedPublicId,
                        '/' . $cleanPublicId . '.' . $extension,
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
                'message' => 'Error al subir archivo: ' . $e->getMessage(),
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

            $images = ["png", "jpg", "jpeg", "webp"];
            if(in_array($format, $images)){
                $resourceType = "image";
            }else{
                $resourceType = "raw";
            }

            $cloudName = env('CLOUDINARY_CLOUD_NAME');
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
                'message' => 'Error al generar la URL: ' . $e->getMessage(),
                'data' => [],
            ];
        }
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

            $result = $this->uploadApi->destroy(
                $publicId,
                [
                    'resource_type' => $resourceType,
                ]
            );

            if (($result['result'] ?? null) !== 'ok') {
                return [
                    'error' => true,
                    'message' => 'Error al eliminar archivo.',
                ];
            }

            return [
                'error' => false,
                'message' => 'Archivo eliminado correctamente.',
            ];
        } catch (\Throwable $e) {

            Log::error(
                'Cloudinary Delete Error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return [
                'error' => true,
                'message' => 'Error al eliminar archivo: ' . $e->getMessage(),
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
    private function validateFile(UploadedFile $file): array
    {
        $maxSize = 10 * 1024 * 1024;

        if ($file->getSize() > $maxSize) {
            return [
                'error' => true,
                'message' => 'El archivo excede 10MB.',
            ];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
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

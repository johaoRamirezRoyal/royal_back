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

            /**
             * Validar archivo
             */
            $validation = $this->validateFile($file);

            if ($validation['error']) {
                return [
                    'error' => true,
                    'message' => $validation['message'],
                    'data' => [],
                ];
            }

            /**
             * Sanitizar folder
             */
            $folder = preg_replace(
                '/[^A-Za-z0-9_\-\/]/',
                '',
                $folder
            );

            /**
             * Tipo de recurso
             */
            $resourceType = $this->getResourceType($file);

            /**
             * Upload y Estructuración de Nombres
             */
            // 1. Obtenemos la extensión limpia directamente del archivo original
            $extension = strtolower($file->getClientOriginalExtension());

            // 2. Obtenemos el nombre base original de forma segura
            $originalName = $file->getClientOriginalName();

            // 3. Quitamos la extensión (.pdf, .jpg, etc) y posibles rastros de .tmp del nombre base
            $originalName = preg_replace('/\.'.preg_quote($extension, '/').'$/i', '', $originalName);
            $originalName = preg_replace('/\.tmp$/i', '', $originalName);

            // 4. Reemplazamos cualquier carácter extraño por guiones bajos
            $originalName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $originalName);

            /**
             * Si es un recurso 'raw', el public_id DEBE incluir la extensión pura (.pdf)
             */
            $publicIdWithExtension = $originalName;

            $options = [
                'folder' => $folder,
                'resource_type' => $resourceType,
                'use_filename' => true,
                'unique_filename' => false,
                'overwrite' => true,
                'public_id' => $publicIdWithExtension,
            ];

            $response = $this->uploadApi->upload(
                $file->getRealPath(),
                $options
            );

            if (! $response) {
                throw new \Exception(
                    'Cloudinary devolvió una respuesta vacía.'
                );
            }

            return [
                'error' => false,
                'message' => 'Archivo subido correctamente.',
                'data' => [
                    'public_id' => $response['public_id'],
                    'url' => $response['secure_url'],
                    'format' => $extension,
                    'size' => $response['bytes'] ?? null,
                    'resource_type' => $response['resource_type'] ?? null,
                ],
            ];

        } catch (\Throwable $e) {

            Log::error(
                'Cloudinary Upload Error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return [
                'error' => true,
                'message' => 'Error al subir archivo: '.$e->getMessage(),
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
            return 'raw';
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

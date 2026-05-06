<?php
namespace App\Services\Cloudinary;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class CloudinaryService
{

    protected $uploadApi;

    public function __construct()
    {
        // Inicializamos la configuración manualmente usando lo que ya tienes en el config
        $config = Configuration::instance(config('cloudinary.cloud_url'));
        $this->uploadApi = new UploadApi($config);
    }

    /**
     * Summary of uploadFile
     * @param UploadedFile $file
     * @param string $folder
     * @return array{data: array, error: bool, message: string|array{data: array, error: mixed, message: mixed}|array{data: array{format: mixed, public_id: mixed, size: mixed, url: mixed}, error: bool, message: string}}
     */
    public function uploadFile(UploadedFile $file, string $folder = "uploads"): array
    {
        try{
            // 1. Verifica que la validación no devuelva null
            $validation = $this->validateFile($file);

            if (!$validation || (isset($validation['error']) && $validation['error'])) {
                return [
                    'error' => true,
                    'message' => $validation['message'] ?? 'Error de validación interna',
                    'data' => []
                ];
            }

            // 2. Limpieza de folder y tipo de recurso
            $resourceType = $this->getResourceType($file) ?? 'auto'; // Fallback a auto
            $folder = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $folder);

            // 3. Ejecución de la subida
            $uploadedFile = $this->uploadApi->upload($file->getRealPath(), [
                'folder'          => $folder,
                'resource_type'   => $resourceType,
                'use_filename'    => false,
                'unique_filename' => true,
            ]);

            // VERIFICACIÓN CRÍTICA:
            if (!$uploadedFile) {
                throw new \Exception("Cloudinary devolvió una respuesta vacía (null).");
            }

            $resultado = [
                'public_id' => $uploadedFile['public_id'],
                'url'       => $uploadedFile['secure_url'],
                'format'    => $uploadedFile['format'] ?? null,
                'size'      => $uploadedFile['bytes'],
            ];
    
            return [
                'error' => false,
                'message' => 'Archivo subido correctamente',
                'data' => $resultado,
            ];

        } catch (\Throwable $e) {
            Log::error('Cloudinary Service Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Handle the exception (log it, throw a custom exception, etc.)
            return [
                'error' => true,
                'message' => 'Error en el servidor: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Eliminar el archivo en cloudinary usando el public_id
     * @param string $publicId
     * @return array{error: bool, message: string}
     */
    public function deleteFile(string $publicId): array
    {
        try{
            if(empty($publicId)){
                return [
                    'error' => true,
                    'message' => 'El public_id no puede estar vacío.',
                ];
            }

            $result = $this->uploadApi->destroy($publicId);
            if(($result['result'] ?? null) !== 'ok'){
                return [
                    'error' => true,
                    'message' => 'Error al eliminar el archivo: ' . ($result['result'] ?? 'Desconocido'),
                ];
            }

            return[
                'error' => false,
                'message' => 'Archivo Eliminado Correctamente',
            ];
        }catch(\Exception $e){
            Log::error('Cloudinary delete error: ' . $e->getMessage());
            // Handle the exception (log it, throw a custom exception, etc.)
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener el tipo de archivo subido (image, video, raw, auto) basado en su MIME type o extensión
     * @param UploadedFile $file
     * @return string
     */
    private function getResourceType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if (str_contains($mime, 'image')) return 'image';
        if (str_contains($mime, 'video')) return 'video';

        if(in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])){
            return 'raw';
        }

        // PDFs, Word, Excel, etc., entran en 'raw' o 'auto'
        return 'auto';
    }

    /**
     * Validación del archivo (Tamaño y Extensión)
     * @param UploadedFile $file
     * @return array{error: bool, message: string}
     */
    private function validateFile(UploadedFile $file): array {
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if($file->getSize() > $maxSize){
            return [
                'error' => true,
                'message' => 'El archivo excede el tamaño máximo permitido de 10 MB.'
            ];
        }

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf'
        ];

        if(!in_array($file->getMimeType(), $allowedMimeTypes)){
            return [
                'error' => true,
                'message' => 'Tipo de archivo no permitido. Solo se permiten JPEG, PNG, WEBP y PDF.'
            ];
        }

        return [
            'error' => false,
            'message' => 'Archivo válido.'
        ];

    }
}


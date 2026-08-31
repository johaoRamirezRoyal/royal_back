<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Disco de uploads configurado (sami en el VPS, public en local).
     */
    private function resolveDisk(?string $disk): string {
        return $disk ?: config('filesystems.uploads_disk', 'public');
    }

    /**
     * Guardar archivo
     */
    public function uploadFile(UploadedFile $archivo, string $carpeta = 'uploads', ?string $disk = null): array {
        $disk = $this->resolveDisk($disk);
        $nombre = Str::uuid() . '.' . $archivo->getClientOriginalExtension();

        $ruta = $archivo->storeAs($carpeta, $nombre, $disk);

        return [
            'nombre_original' => $archivo->getClientOriginalName(),
            'nombre_guardado' => $nombre,
            'ruta' => $ruta,
            'url' => Storage::disk($disk)->url($ruta)
        ];
    }

    /**
     * URL pública de un archivo ya guardado en el disco de uploads.
     */
    public function url(?string $nombre, ?string $disk = null): ?string {
        if (!$nombre) {
            return null;
        }

        return Storage::disk($this->resolveDisk($disk))->url($nombre);
    }

    /**
     * Eliminar archivo
     */
    public function eliminar(?string $ruta, ?string $disk = null): bool {
        if (!$ruta) {
            return false;
        }

        $disk = $this->resolveDisk($disk);

        if (Storage::disk($disk)->exists($ruta)) {
            return Storage::disk($disk)->delete($ruta);
        }

        return false;
    }

    /**
     * Reemplazar archivo
     */
    public function reemplazar(UploadedFile $nuevoArchivo, ?string $archivoAnterior, string $carpeta = 'uploads', ?string $disk = null): array {
        $disk = $this->resolveDisk($disk);
        $this->eliminar($archivoAnterior, $disk);

        return $this->uploadFile(
            $nuevoArchivo,
            $carpeta,
            $disk
        );
    }
}

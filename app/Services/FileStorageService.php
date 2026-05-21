<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Guardar archivo
     */
    public function guardar(UploadedFile $archivo, string $carpeta = 'uploads', string $disk = 'public'): array {
        $nombre = Str::uuid() . '.' . $archivo->getClientOriginalExtension();

        $ruta = $archivo->storeAs($carpeta, $nombre, $disk);

        return [
            'nombre_original' => $archivo->getClientOriginalName(),
            'nombre_guardado' => $nombre,
            'ruta' => $ruta,
            'url' => Storage::url($ruta)
        ];
    }

    /**
     * Eliminar archivo
     */
    public function eliminar(?string $ruta, string $disk = 'public'): bool {
        if (!$ruta) {
            return false;
        }

        if (Storage::disk($disk)->exists($ruta)) {
            return Storage::disk($disk)->delete($ruta);
        }

        return false;
    }

    /**
     * Reemplazar archivo
     */
    public function reemplazar(UploadedFile $nuevoArchivo, ?string $archivoAnterior, string $carpeta = 'uploads', string $disk = 'public'): array {
        $this->eliminar($archivoAnterior, $disk);

        return $this->guardar(
            $nuevoArchivo,
            $carpeta,
            $disk
        );
    }
}

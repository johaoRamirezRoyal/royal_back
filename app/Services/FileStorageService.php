<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
     * Ruta local del archivo, para lo que necesita un path real (GD, adjuntos de correo).
     * En un disco local es el archivo mismo; en uno remoto (Cloudinary) se descarga a
     * storage/app/archivos-remotos y se reusa: las rutas llevan UUID, no se reescriben.
     * null si no existe.
     */
    public function rutaLocal(?string $ruta, ?string $disk = null): ?string {
        if (!$ruta || str_contains($ruta, '..')) {
            return null;
        }

        $disk = $this->resolveDisk($disk);
        $disco = Storage::disk($disk);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return $disco->exists($ruta) ? $disco->path($ruta) : null;
        }

        $local = storage_path('app/archivos-remotos/' . sha1($ruta) . '.' . pathinfo($ruta, PATHINFO_EXTENSION));
        if (is_file($local)) {
            return $local;
        }

        $contenido = $disco->get($ruta);
        if ($contenido === null) {
            return null;
        }

        // Se escribe aparte y se renombra: dos peticiones a la vez nunca leen un archivo a medias.
        @mkdir(dirname($local), 0775, true);
        $temporal = $local . '.' . uniqid('', true);
        file_put_contents($temporal, $contenido);

        return rename($temporal, $local) ? $local : null;
    }

    /**
     * Cuáles de estas rutas existen, como [ruta => bool]. En un disco remoto pregunta todas a
     * la vez (HEAD a la URL pública) en vez de una por una.
     */
    public function existentes(array $rutas, ?string $disk = null): array {
        $rutas = array_values(array_unique(array_filter($rutas, fn ($r) => $r && !str_contains($r, '..'))));
        $disk = $this->resolveDisk($disk);
        $disco = Storage::disk($disk);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return array_combine($rutas, array_map(fn ($r) => $disco->exists($r), $rutas));
        }

        $respuestas = [];
        foreach (array_chunk($rutas, 50) as $lote) {
            $respuestas += Http::pool(fn (Pool $pool) => array_map(
                fn ($r) => $pool->as($r)->timeout(10)->head($disco->url($r)),
                $lote
            ));
        }

        return array_map(fn ($r) => $r instanceof Response && $r->successful(), $respuestas);
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

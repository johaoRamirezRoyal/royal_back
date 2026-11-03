<?php

namespace App\Console\Commands;

use App\Services\AdminManagement\BasesDatosService;
use App\Services\Cloudinary\ArchivosPrivados;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pasa a `authenticated` los archivos privados que ya estaban subidos como `upload` (públicos):
 * las carpetas de ArchivosPrivados y la raíz de cada colegio. rename con
 * to_type no vuelve a subir el archivo e invalida la caché del CDN. Se puede correr las veces
 * que haga falta: solo lista lo que sigue en `upload`.
 *
 * Gasta Admin API (cuota por hora) en listar: una llamada por cada 500 archivos.
 *
 * `--carpeta=` (repetible) agrega carpetas raíz que ya no están en "Bases de datos": al cambiar
 * la carpeta de un colegio sus archivos no se mueven, y su raíz vieja dejaría de reconocerse.
 */
class CloudinaryPrivatizarCommand extends Command
{
    protected $signature = 'cloudinary:privatizar
        {--dry-run : Solo lista lo que pasaría a privado}
        {--carpeta=* : Carpeta raíz anterior de un colegio, además de las de "Bases de datos"}';

    protected $description = 'Pasa a privados (authenticated) los archivos sensibles que siguen públicos en Cloudinary';

    public function handle(): int
    {
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
        ]);

        $carpetas = [
            ...array_map(BasesDatosService::carpeta(...), BasesDatosService::connectionsConUsuarios()),
            ...array_map(fn ($c) => trim($c, '/'), $this->option('carpeta')),
        ];
        $this->line('Raíces privadas: ' . implode(', ', array_unique($carpetas)));
        $privado = fn (string $id) => ArchivosPrivados::esPrivado($id) || in_array(dirname($id), $carpetas, true);
        $total = $fallidos = 0;

        foreach (['image', 'raw'] as $tipo) {
            $cursor = null;
            do {
                $pagina = $cloudinary->adminApi()->assets(array_filter([
                    'resource_type' => $tipo,
                    'type' => 'upload',
                    'max_results' => 500,
                    'next_cursor' => $cursor,
                ]));

                foreach ($pagina['resources'] ?? [] as $recurso) {
                    $id = $recurso['public_id'];
                    if (! $privado($id)) {
                        continue;
                    }

                    $total++;
                    $this->line("{$tipo}: {$id}");
                    if ($this->option('dry-run')) {
                        continue;
                    }

                    try {
                        $cloudinary->uploadApi()->rename($id, $id, [
                            'resource_type' => $tipo,
                            'type' => 'upload',
                            'to_type' => 'authenticated',
                            'invalidate' => true,
                        ]);
                    } catch (Throwable $e) {
                        $fallidos++;
                        $this->error("  falló: {$e->getMessage()}");
                    }
                }

                $cursor = $pagina['next_cursor'] ?? null;
            } while ($cursor);
        }

        $this->info($this->option('dry-run')
            ? "{$total} archivos pasarían a privados."
            : ($total - $fallidos) . " archivos pasaron a privados, {$fallidos} fallaron.");

        return $fallidos ? self::FAILURE : self::SUCCESS;
    }
}

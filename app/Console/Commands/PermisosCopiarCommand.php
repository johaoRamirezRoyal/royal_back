<?php

namespace App\Console\Commands;

use App\Services\AdminManagement\BasesDatosService;
use App\Support\Permiso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Los números de perfil cambian de un colegio a otro, así que los permisos (cron_permisos) de un
 * colegio no se pueden copiar por id. Este comando empareja los perfiles por NOMBRE y otorga en
 * el destino las opciones que el perfil homónimo tiene en el origen. Solo agrega: nunca quita
 * nada de lo que el destino configuró en /permisos. Sin --aplicar solo muestra lo que haría.
 */
class PermisosCopiarCommand extends Command
{
    protected $signature = 'permisos:copiar
        {origen : Connection de la que se leen los permisos (p. ej. mysql = Royal)}
        {destino : Connection en la que se otorgan (p. ej. sami_hebreo)}
        {--aplicar : Escribe los permisos; sin esto solo los lista}';

    protected $description = 'Otorga en un colegio las opciones que tiene en otro el perfil del mismo nombre (solo agrega)';

    public function handle(): int
    {
        [$origen, $destino] = [$this->argument('origen'), $this->argument('destino')];

        foreach ([$origen, $destino] as $connection) {
            if (! BasesDatosService::esConnectionValida($connection) || $connection === 'admin_management') {
                $this->error("\"{$connection}\" no es la base de un colegio.");
                return self::FAILURE;
            }
        }
        if ($origen === $destino) {
            $this->error('El origen y el destino son la misma base.');
            return self::FAILURE;
        }

        $norm = fn ($nombre) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $nombre)));
        $perfilesOrigen = DB::connection($origen)->table('perfiles')->pluck('nombre', 'id_perfil')->map($norm);
        $perfilesDestino = DB::connection($destino)->table('perfiles')->pluck('nombre', 'id_perfil')->map($norm)->flip();
        $opcionesDestino = DB::connection($destino)->table('cron_opciones')->pluck('nombre', 'id');

        $otorgadas = fn (string $connection) => DB::connection($connection)->table('cron_permisos')->where('activo', 1)
            ->get(['id_perfil', 'id_opcion'])->groupBy('id_perfil')
            ->map(fn ($filas) => $filas->pluck('id_opcion')->map(fn ($id) => (int) $id)->unique()->all());
        [$enOrigen, $enDestino] = [$otorgadas($origen), $otorgadas($destino)];

        $nuevas = [];
        $sinPareja = [];
        foreach ($perfilesOrigen as $idOrigen => $nombre) {
            // Super Admin tiene todo sin filas en cron_permisos (y no se le pueden crear).
            if ((int) $idOrigen === Permiso::PERFIL_SUPER_ADMIN || ! isset($enOrigen[$idOrigen])) {
                continue;
            }
            $idDestino = $perfilesDestino[$nombre] ?? null;
            if ($idDestino === null) {
                $sinPareja[] = $nombre;
                continue;
            }
            if ((int) $idDestino === Permiso::PERFIL_SUPER_ADMIN) {
                continue;
            }

            $faltan = array_diff($enOrigen[$idOrigen], $enDestino[$idDestino] ?? []);
            $faltan = array_values(array_filter($faltan, fn ($opcion) => $opcionesDestino->has($opcion)));
            if (! $faltan) {
                continue;
            }

            sort($faltan);
            $this->line("<info>{$nombre}</info> ({$destino} #{$idDestino}):");
            foreach ($faltan as $opcion) {
                $this->line("   + {$opcion} {$opcionesDestino[$opcion]}");
                $nuevas[] = ['id_opcion' => $opcion, 'id_perfil' => (int) $idDestino, 'activo' => 1, 'fechareg' => now()];
            }
        }

        if ($sinPareja) {
            $this->warn('Perfiles de ' . $origen . ' sin uno del mismo nombre en ' . $destino . ' (se omiten): ' . implode(', ', $sinPareja));
        }

        if (! $nuevas) {
            $this->info('No falta nada: el destino ya tiene todo lo del origen.');
            return self::SUCCESS;
        }

        if (! $this->option('aplicar')) {
            $this->info(count($nuevas) . ' permisos por otorgar. Revisa la lista y vuelve a correr con --aplicar.');
            return self::SUCCESS;
        }

        DB::connection($destino)->transaction(function () use ($destino, $nuevas) {
            // Una fila inactiva del mismo par se reactiva en vez de duplicarse.
            foreach ($nuevas as $fila) {
                $actualizadas = DB::connection($destino)->table('cron_permisos')
                    ->where('id_opcion', $fila['id_opcion'])->where('id_perfil', $fila['id_perfil'])->update(['activo' => 1]);
                if ($actualizadas === 0) {
                    DB::connection($destino)->table('cron_permisos')->insert($fila);
                }
            }
        });

        $this->info(count($nuevas) . " permisos otorgados en {$destino}.");
        return self::SUCCESS;
    }
}

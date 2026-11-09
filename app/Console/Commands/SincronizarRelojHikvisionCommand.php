<?php

namespace App\Console\Commands;

use App\Services\AdminManagement\BasesDatosService;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Revisa (y corrige si hace falta) el reloj de las terminales Hikvision de TODOS los colegios:
 * cada colegio tiene sus propias terminales en su base (ver hikvisionattendanceService::
 * sincronizarRelojes). Un colegio sin la tabla o sin conexión se salta con un aviso.
 */
class SincronizarRelojHikvisionCommand extends Command
{
    protected $signature = 'hikvision:sincronizar-reloj {--solo-revisar : Solo mide el desfase, no corrige}';

    protected $description = 'Compara el reloj de cada terminal Hikvision con el del servidor y lo corrige si se desfasa más de 10 segundos';

    public function handle(): int
    {
        foreach (BasesDatosService::connectionsConUsuarios() as $colegio) {
            Config::set('database.default', $colegio);

            try {
                // Instancia nueva por colegio: el servicio memoiza las terminales del colegio vigente.
                $resultados = (new hikvisionattendanceService())->sincronizarRelojes(!$this->option('solo-revisar'));
            } catch (\Throwable $e) {
                $this->warn("{$colegio}: no se pudieron leer las terminales ({$e->getMessage()})");
                continue;
            }

            foreach ($resultados as $deviceId => $r) {
                $estado = $r['error'] ?? (($r['corregido'] ? 'corregido, ' : '') . "desfase {$r['desfase_segundos']} s");
                $this->line("{$colegio} {$deviceId}: {$estado}");
            }
        }

        return self::SUCCESS;
    }
}

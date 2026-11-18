<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EnCadaColegio;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Console\Command;

class CerrarAsistenciasVencidasCommand extends Command
{
    use EnCadaColegio;

    protected $signature = 'asistencia:cerrar-vencidas';

    protected $description = 'Cierra automáticamente las asistencias del día con entrada pero sin salida cuya hora_salida_esperada ya pasó, y notifica al trabajador y a RH';

    public function handle(AsistenciaGestionService $asistenciaService): int
    {
        return $this->enCadaColegio(fn () => $asistenciaService->cerrarAsistenciasVencidas());
    }
}

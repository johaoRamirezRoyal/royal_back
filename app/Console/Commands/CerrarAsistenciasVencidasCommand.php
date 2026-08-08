<?php

namespace App\Console\Commands;

use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Console\Command;

class CerrarAsistenciasVencidasCommand extends Command
{
    protected $signature = 'asistencia:cerrar-vencidas';

    protected $description = 'Cierra automáticamente las asistencias del día con entrada pero sin salida cuya hora_salida_esperada ya pasó, y notifica al trabajador y a RH';

    public function handle(AsistenciaGestionService $asistenciaService): int
    {
        $resultado = $asistenciaService->cerrarAsistenciasVencidas();

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\encuestas\EncuestasServices;
use Illuminate\Console\Command;

class EnviarConsolidadoEncuestasCommand extends Command
{
    protected $signature = 'encuestas:enviar-consolidado';

    protected $description = 'Envía a cada responsable de una reserva de hoy el PDF consolidado de las encuestas respondidas (ver EncuestasServices::enviarConsolidadosDelDia)';

    public function handle(EncuestasServices $encuestasServices): int
    {
        $resultado = $encuestasServices->enviarConsolidadosDelDia();

        if ($resultado['error']) {
            $this->error($resultado['message']);

            return self::FAILURE;
        }

        $this->info($resultado['message']);

        return self::SUCCESS;
    }
}

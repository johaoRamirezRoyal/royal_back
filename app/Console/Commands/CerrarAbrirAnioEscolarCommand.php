<?php

namespace App\Console\Commands;

use App\Services\AnioEscolar\AnioEscolarServices;
use Illuminate\Console\Command;

class CerrarAbrirAnioEscolarCommand extends Command
{
    protected $signature = 'anio-escolar:cerrar-abrir';

    protected $description = 'Cierra el año escolar vigente si ya terminó y abre/activa el que corresponde según el calendario (A o B) configurado';

    public function handle(AnioEscolarServices $anioEscolarServices): int
    {
        $resultado = $anioEscolarServices->cerrarYAbrirAnioEscolar();

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

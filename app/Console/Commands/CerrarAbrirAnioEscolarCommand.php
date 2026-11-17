<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EnCadaColegio;
use App\Services\AnioEscolar\AnioEscolarServices;
use Illuminate\Console\Command;

class CerrarAbrirAnioEscolarCommand extends Command
{
    use EnCadaColegio;

    protected $signature = 'anio-escolar:cerrar-abrir';

    protected $description = 'Cierra el año escolar vigente si ya terminó y abre/activa el que corresponde según el calendario (A o B) configurado';

    public function handle(AnioEscolarServices $anioEscolarServices): int
    {
        return $this->enCadaColegio(fn () => $anioEscolarServices->cerrarYAbrirAnioEscolar());
    }
}

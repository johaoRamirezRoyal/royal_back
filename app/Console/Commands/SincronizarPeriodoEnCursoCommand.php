<?php

namespace App\Console\Commands;

use App\Services\AnioEscolar\PeriodoServices;
use Illuminate\Console\Command;

class SincronizarPeriodoEnCursoCommand extends Command
{
    protected $signature = 'periodos-administrativos:sincronizar-en-curso';

    protected $description = 'Marca como en curso el periodo administrativo (tabla periodos) cuyas fechas cubren hoy';

    public function handle(PeriodoServices $periodoServices): int
    {
        $resultado = $periodoServices->sincronizarEnCurso();

        if ($resultado['error']) {
            $this->error($resultado['message']);

            return self::FAILURE;
        }

        $this->info($resultado['message']);

        return self::SUCCESS;
    }
}

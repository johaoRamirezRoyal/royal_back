<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EnCadaColegio;
use App\Services\AnioEscolar\PeriodoServices;
use Illuminate\Console\Command;

class SincronizarPeriodoEnCursoCommand extends Command
{
    use EnCadaColegio;

    protected $signature = 'periodos-administrativos:sincronizar-en-curso';

    protected $description = 'Marca como en curso el periodo administrativo (tabla periodos) cuyas fechas cubren hoy';

    public function handle(PeriodoServices $periodoServices): int
    {
        return $this->enCadaColegio(fn () => $periodoServices->sincronizarEnCurso());
    }
}

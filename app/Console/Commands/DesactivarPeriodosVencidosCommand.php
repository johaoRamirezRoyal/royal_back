<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EnCadaColegio;
use App\Services\AnioEscolar\PeriodoAcademicoServices;
use Illuminate\Console\Command;

class DesactivarPeriodosVencidosCommand extends Command
{
    use EnCadaColegio;

    protected $signature = 'periodos:desactivar-vencidos';

    protected $description = 'Desactiva automáticamente los periodos académicos cuya fecha_fin ya pasó';

    public function handle(PeriodoAcademicoServices $periodoAcademicoServices): int
    {
        return $this->enCadaColegio(fn () => $periodoAcademicoServices->desactivarPeriodosVencidos());
    }
}

<?php

namespace App\Console\Commands;

use App\Services\AnioEscolar\PeriodoAcademicoServices;
use Illuminate\Console\Command;

class DesactivarPeriodosVencidosCommand extends Command
{
    protected $signature = 'periodos:desactivar-vencidos';

    protected $description = 'Desactiva automáticamente los periodos académicos cuya fecha_fin ya pasó';

    public function handle(PeriodoAcademicoServices $periodoAcademicoServices): int
    {
        $resultado = $periodoAcademicoServices->desactivarPeriodosVencidos();

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

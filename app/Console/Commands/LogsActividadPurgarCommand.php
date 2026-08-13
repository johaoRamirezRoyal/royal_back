<?php

namespace App\Console\Commands;

use App\Services\LogsActividad\LogsActividadService;
use Illuminate\Console\Command;

class LogsActividadPurgarCommand extends Command
{
    protected $signature = 'logs-actividad:purgar-antiguos {--dias=90 : Días de retención; se borra todo lo anterior}';

    protected $description = 'Borra los logs de actividad más viejos que el número de días de retención indicado';

    public function handle(LogsActividadService $logsActividadService): int
    {
        $resultado = $logsActividadService->purgarLogsAntiguos((int) $this->option('dias'));

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

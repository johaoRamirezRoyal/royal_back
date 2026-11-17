<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EnCadaColegio;
use App\Services\LogsActividad\LogsActividadService;
use Illuminate\Console\Command;

class LogsActividadPurgarCommand extends Command
{
    use EnCadaColegio;

    protected $signature = 'logs-actividad:purgar-antiguos {--dias=90 : Días de retención; se borra todo lo anterior}';

    protected $description = 'Borra los logs de actividad más viejos que el número de días de retención indicado';

    public function handle(LogsActividadService $logsActividadService): int
    {
        return $this->enCadaColegio(fn () => $logsActividadService->purgarLogsAntiguos((int) $this->option('dias')));
    }
}

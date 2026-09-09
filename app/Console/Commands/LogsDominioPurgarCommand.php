<?php

namespace App\Console\Commands;

use App\Services\AdminManagement\LogDominioService;
use Illuminate\Console\Command;

class LogsDominioPurgarCommand extends Command
{
    protected $signature = 'logs-dominio:purgar-antiguos {--dias=90 : Días de retención; se borra todo lo anterior}';

    protected $description = 'Borra los logs por dominio (admin_management.logs_dominio) más viejos que el número de días de retención indicado';

    public function handle(LogDominioService $logDominioService): int
    {
        $resultado = $logDominioService->purgarLogsAntiguos((int) $this->option('dias'));

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

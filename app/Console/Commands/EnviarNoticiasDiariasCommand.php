<?php

namespace App\Console\Commands;

use App\Services\Noticias\NoticiasService;
use Illuminate\Console\Command;

class EnviarNoticiasDiariasCommand extends Command
{
    protected $signature = 'noticias:enviar-diarias';

    protected $description = 'Envía por correo el mensaje general y los cumpleaños programados de hoy; los marcados repetir_anual se repiten cada año (ver NoticiasService::enviarPendientesDelDia)';

    public function handle(NoticiasService $noticiasService): int
    {
        $resultado = $noticiasService->enviarPendientesDelDia();

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

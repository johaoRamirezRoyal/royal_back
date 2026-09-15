<?php

namespace App\Console\Commands;

use App\Services\Noticias\NoticiasService;
use Illuminate\Console\Command;

class EnviarNoticiasDiariasCommand extends Command
{
    protected $signature = 'noticias:enviar-diarias';

    protected $description = 'Envía por correo el mensaje general (una vez al día) y las noticias programadas cuya fecha es hoy (ver NoticiasService::enviarPendientesDelDia)';

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

<?php

namespace App\Console\Commands;

use App\Services\Noticias\CumpleanosService;
use App\Services\Noticias\NoticiasService;
use Illuminate\Console\Command;

class EnviarNoticiasDiariasCommand extends Command
{
    protected $signature = 'noticias:enviar-diarias';

    protected $description = 'Genera las felicitaciones automáticas de cumpleaños y envía por correo el mensaje general y los cumpleaños programados de hoy; los marcados repetir_anual se repiten cada año (ver NoticiasService::enviarPendientesDelDia)';

    public function handle(NoticiasService $noticiasService, CumpleanosService $cumpleanosService): int
    {
        // Primero se generan las felicitaciones automáticas que ya entraron en su ventana:
        // quedan como noticias programadas de tipo cumpleaños y el envío de abajo las manda
        // el día que corresponde (ver CumpleanosService::generarPendientes).
        $this->info($cumpleanosService->generarPendientes()['message']);

        $resultado = $noticiasService->enviarPendientesDelDia();

        if ($resultado['error']) {
            $this->error($resultado['message']);
            return self::FAILURE;
        }

        $this->info($resultado['message']);
        return self::SUCCESS;
    }
}

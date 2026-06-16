<?php

namespace App\Listeners;

use App\Events\PasswordRestore;
use App\Mail\PasswordRestoreEmail;
use App\Services\MailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPasswordRestore
{
    public int $tries = 3;        // reintentos si falla
    public int $backoff = 10;     // segundos entre reintentos

    public function __construct(
        private MailService $mailService
    ) {}

     public function handle(PasswordRestore $event): void
    {
        $this->mailService->send(
            $event->user->correo,
            new PasswordRestoreEmail($event->user, $event->token)
        );
    }

        public function failed(PasswordRestore $event, \Throwable $e): void
    {
        // si falla los 3 intentos, puedes loggear o notificar
        Log::error("Falló el envío de email a {$event->user->email}: {$e->getMessage()}");
    }
}

<?php

namespace App\Listeners;

use App\Events\PasswordRestore;
use App\Mail\PasswordRestoreEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPasswordRestore
{
    public int $tries = 3;        // reintentos si falla
    public int $backoff = 10;     // segundos entre reintentos

     public function handle(PasswordRestore $event): void
    {
        Mail::to($event->user->correo)  // ✅ $pass en lugar de $user
            ->send(new PasswordRestoreEmail($event->user, $event->token));
    }

        public function failed(PasswordRestore $event, \Throwable $e): void
    {
        // si falla los 3 intentos, puedes loggear o notificar
        Log::error("Falló el envío de email a {$event->user->email}: {$e->getMessage()}");
    }
}

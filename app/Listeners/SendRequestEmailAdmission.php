<?php

namespace App\Listeners;

use App\Events\RequestEmailAdmission;
use App\Mail\RequestEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class SendRequestEmailAdmission implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'emails';
    public int $tries = 3;
    public int $backoff = 60; // segundos entre reintentos

    public function handle(RequestEmailAdmission $event): void
    {
        Log::info("Enviando correo de admisión", ['email' => $event->email]);

        Mail::to($event->email)
            ->send(new RequestEmail($event->email, $event->token, $event->verificationCode));
    }

    public function failed(RequestEmailAdmission $event, \Throwable $e): void
    {
        Log::error("Falló el envío de email de admisión", [
            'email'   => $event->email,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
    }
}
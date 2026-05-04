<?php

namespace App\Listeners;

use App\Events\RequestEmailAdmission;
use App\Mail\RequestEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRequestEmailAdmission
{
    public $queue = 'emails';

    /**
     * Handle the event.
     */
    public function handle(RequestEmailAdmission $event): void
    {
        Mail::to($event->email)->send(new RequestEmail($event->email, $event->token, $event->verificationCode));
    }

    public function failed(RequestEmailAdmission $event, \Throwable $e): void
    {
        // si falla los 3 intentos, puedes loggear o notificar
        Log::error("Falló el envío de email a {$event->email}: {$e->getMessage()}");
    }
}

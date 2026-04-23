<?php

namespace App\Listeners;

use App\Events\PasswordRestore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPasswordRestore
{
    public int $tries = 3;        // reintentos si falla
    public int $backoff = 10;     // segundos entre reintentos

    public function handle(PasswordRestore $event): void
    {
        Mail::to($event->pass->email)
                ->send(new PasswordRestore($event->pass, $event->token));
    }
}

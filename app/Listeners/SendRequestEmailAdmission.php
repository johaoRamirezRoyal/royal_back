<?php

namespace App\Listeners;

use App\Events\RequestEmailAdmission;
use App\Mail\RequestEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRequestEmailAdmission
{
    public function handle(RequestEmailAdmission $event): void
    {
        Log::info("Enviando correo de admisión", ['email' => $event->email]);

        Mail::to($event->email)
            ->send(new RequestEmail($event->email, $event->token, $event->verificationCode));
    }
}
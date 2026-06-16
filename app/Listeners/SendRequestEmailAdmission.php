<?php

namespace App\Listeners;

use App\Events\RequestEmailAdmission;
use App\Mail\RequestEmail;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class SendRequestEmailAdmission
{
    public function __construct(
        private MailService $mailService
    ) {}

    public function handle(RequestEmailAdmission $event): void
    {
        Log::info("Enviando correo de admisión", ['email' => $event->email]);

        $this->mailService->send(
            $event->email,
            new RequestEmail($event->email, $event->token, $event->verificationCode)
        );
    }
}
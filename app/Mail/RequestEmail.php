<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RequestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;

    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $verificationCode
    ) {
        Log::info("Enviando 'resource' de email");
        $this->url = rtrim(config('app.frontend_url'), '/') . '/admissions';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->email],
            subject: 'Verifica tu correo — Admisiones Royal School',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.request-email',
        );
    }
}
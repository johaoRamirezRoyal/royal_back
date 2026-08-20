<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LlegadaTardeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $asunto,
        public readonly string $nombreEstudiante,
        public readonly string $grado,
        public readonly string $fecha,
        public readonly int $totalEnPeriodo,
        public readonly string $periodo,
        public readonly bool $limiteAlcanzado,
        public readonly int $cantidadLimite = 5,
        public readonly bool $advertencia = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.llegadaTarde');
    }
}

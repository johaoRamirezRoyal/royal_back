<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LlegadaTardeAvisoInternoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombreEstudiante,
        public readonly string $documento,
        public readonly string $grado,
        public readonly int $totalEnPeriodo,
        public readonly string $periodo,
        public readonly string $fecha,
        public readonly string $hora,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Falta por llegadas tarde acumuladas');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.llegadaTardeAvisoInterno');
    }
}

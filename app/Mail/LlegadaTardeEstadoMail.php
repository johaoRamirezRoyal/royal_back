<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso informativo al acudiente cuando una llegada tarde queda justificada
 * (se registra con soporte) o se revoca — dos eventos administrativos sobre
 * un registro ya existente, a diferencia de LlegadaTardeMail que informa el
 * registro mismo de la llegada tarde.
 */
class LlegadaTardeEstadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $tipo, // 'justificada' | 'revocada'
        public readonly string $nombreEstudiante,
        public readonly string $grado,
        public readonly string $fecha,
        public readonly ?string $observacion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tipo === 'justificada'
                ? 'Llegada tarde justificada'
                : 'Revocación de llegada tarde'
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.llegadaTardeEstado');
    }
}

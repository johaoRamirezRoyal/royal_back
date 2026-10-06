<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de "Mostrar encuesta" al crear una reserva (ver ReservasServices::enviarCorreoEncuesta):
 * info de la reserva + el JPG con el QR de la encuesta del salón, embebido al final del
 * cuerpo y también adjunto para que el usuario lo descargue y lo comparta.
 */
class ReservaEncuestaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $reserva,
        public readonly string $jpg,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva ' . $this->reserva['salon'] . ' — QR de la encuesta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservaEncuesta',
            with: ['reserva' => $this->reserva, 'jpg' => $this->jpg],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->jpg, 'qr-encuesta-reserva.jpg')->withMime('image/jpeg'),
        ];
    }
}

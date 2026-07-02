<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioPrestamosEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombre,
        public readonly int $cantidadLibros,
        public readonly string $pdfContenido,
        public readonly string $pdfNombre
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de préstamos pendientes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorioPrestamos',
            with: [
                'nombre' => $this->nombre,
                'cantidadLibros' => $this->cantidadLibros,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdfContenido, $this->pdfNombre)
                ->withMime('application/pdf'),
        ];
    }
}

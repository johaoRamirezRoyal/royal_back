<?php

namespace App\Mail\Instituciones;

use App\Models\Instituciones\CartaRecomendacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CartaRecomendacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CartaRecomendacion $carta,
        public readonly string $pdfContenido,
        public readonly string $pdfNombre,
    ) {
    }

    public function envelope(): Envelope
    {
        $nombreEstudiante = $this->carta->datos['nombre_estudiante'] ?? 'aspirante';

        return new Envelope(
            subject: "Carta de recomendación — {$nombreEstudiante} ({$this->carta->institucion?->nombre})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cartaRecomendacion',
            with: [
                'nombreInstitucion' => $this->carta->institucion?->nombre ?? '',
                'nombreEstudiante' => $this->carta->datos['nombre_estudiante'] ?? '',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContenido, $this->pdfNombre)
                ->withMime('application/pdf'),
        ];
    }
}

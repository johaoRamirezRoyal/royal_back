<?php

namespace App\Mail;

use App\Services\branding\MarcaDominioService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Consolidado diario (17:00) de la encuesta de una reserva, al responsable de la reserva
 * (ver EncuestasServices::enviarConsolidadosDelDia).
 */
class EncuestaConsolidadoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{nombre: string, correo: string, reserva: ?string, salon: string, encuesta: string, fecha: string}  $datos
     */
    public function __construct(
        public readonly array $datos,
        public readonly string $pdfContenido,
        public readonly string $pdfNombre,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resultados de la encuesta — '.($this->datos['reserva'] ?: $this->datos['encuesta']),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.encuestaConsolidado',
            with: [...$this->datos, 'nombreColegio' => app(MarcaDominioService::class)->nombreInstitucion($this->datos['correo'])],
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

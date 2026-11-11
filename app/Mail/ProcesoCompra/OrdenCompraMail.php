<?php

namespace App\Mail\ProcesoCompra;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Orden de compra para el proveedor, redactada en el frontend. `$mensajeHtml` llega ya
 * saneado (HtmlCorreoSanitizer); `$bloques` los arma SolicitudesServices desde la BD. El
 * PDF lo arma el frontend (OrdenCompraPDF.ts) y es opcional. `$correosContacto` (grupo
 * COMPRAS_VENTAS) se muestra en el cuerpo y va como responder-a.
 */
class OrdenCompraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $numeroOrden,
        public readonly string $asunto,
        public readonly string $mensajeHtml,
        /** @var array<string, mixed> */
        public readonly array $bloques = [],
        /** @var array<int, string> */
        public readonly array $correosContacto = [],
        /** @var array<int, string> */
        public readonly array $correosCc = [],
        public readonly ?string $pdfContenido = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto,
            cc: array_map(fn (string $correo) => new Address($correo), $this->correosCc),
            replyTo: array_map(fn (string $correo) => new Address($correo), $this->correosContacto),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordenCompra',
            with: [
                'numeroOrden' => $this->numeroOrden,
                'mensajeHtml' => $this->mensajeHtml,
                'bloques' => $this->bloques,
                'correosContacto' => $this->correosContacto,
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->pdfContenido === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfContenido, "Orden_compra_{$this->numeroOrden}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

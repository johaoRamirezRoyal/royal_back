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
 * Orden de compra para el proveedor. El PDF lo arma el frontend (OrdenCompraPDF.ts) y llega
 * subido; aquí solo se adjunta. `$correosContacto` (grupo COMPRAS_VENTAS) se muestra en el
 * cuerpo y va como responder-a, para que la respuesta del proveedor llegue a Ventas.
 */
class OrdenCompraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $numeroOrden,
        public readonly string $nombreProveedor,
        public readonly string $pdfContenido,
        /** @var array<int, string> */
        public readonly array $correosContacto = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Orden de compra No. {$this->numeroOrden} — ".config('app.name'),
            replyTo: array_map(fn (string $correo) => new Address($correo), $this->correosContacto),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordenCompra',
            with: [
                'numeroOrden' => $this->numeroOrden,
                'nombreProveedor' => $this->nombreProveedor,
                'correosContacto' => $this->correosContacto,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContenido, "Orden_compra_{$this->numeroOrden}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

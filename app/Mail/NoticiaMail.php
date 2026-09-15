<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Correo de una noticia (general o programada, ver NoticiasService::enviarPendientesDelDia)
 * — un solo Mailable para ambos casos, ya que comparten exactamente los mismos campos
 * (titulo/mensaje/url/imagen). Envío síncrono vía MailService::send (Mail::to()->send()),
 * no encolado — mismo patrón que el resto de Mailables de este proyecto (GenericMail,
 * EvaluacionRespuestaMail, ninguno implementa ShouldQueue); el comando que dispara esto
 * corre en cron sin nadie esperando la respuesta, así que bloquear su propio runtime no
 * es un problema de UX. ponytail: si el volumen de destinatarios crece mucho, ahí sí
 * vale la pena encolar (ShouldQueue + Mail::to()->queue()), no antes.
 */
class NoticiaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $titulo,
        public readonly ?string $mensaje,
        public readonly ?string $url = null,
        public readonly ?string $imagenRuta = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.noticia',
            with: [
                'titulo' => $this->titulo,
                'mensaje' => $this->mensaje,
                'url' => $this->url,
            ],
        );
    }

    /** Como adjunto regular, no incrustada en el cuerpo (cid) — evita depender de que
     * el cliente de correo del destinatario resuelva bien el embed inline, que varía
     * entre proveedores. Silenciosamente sin adjunto si no hay imagen o ya no existe en
     * disco (mismo criterio "no bloquear el envío por esto" que el resto del módulo). */
    public function attachments(): array
    {
        if (!$this->imagenRuta) {
            return [];
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($this->imagenRuta)) {
            return [];
        }

        return [
            Attachment::fromPath($disk->path($this->imagenRuta)),
        ];
    }
}

<?php

namespace App\Mail;

use App\Services\FileStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenericMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $titulo;

    public string $contenido;

    public ?string $urlBoton;

    public string $textoBoton;

    /**
     * @param  array<int, array{ruta: string, nombre: string}>  $adjuntos  Archivos del disco de uploads a adjuntar de verdad.
     */
    public function __construct(string $titulo, string $contenido, ?string $urlBoton = null, string $textoBoton = 'Ver en OMNIA', public array $adjuntos = [])
    {
        $this->titulo = $titulo;
        $this->contenido = $contenido;
        $this->urlBoton = $urlBoton;
        $this->textoBoton = $textoBoton;
    }

    public function build()
    {
        $this->subject($this->titulo)->view('emails.generic');

        foreach ($this->adjuntos as $adjunto) {
            $local = app(FileStorageService::class)->rutaLocal($adjunto['ruta']);

            if (! $local) {
                // Un archivo ilegible no tumba el correo: sale con los demás y queda en el log.
                Log::error('Adjunto de correo no disponible', $adjunto);

                continue;
            }

            $this->attach($local, ['as' => $adjunto['nombre']]);
        }

        return $this;
    }

    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Generic Mail',
    //     );
    // }

    // /**
    //  * Get the message content definition.
    //  */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    // /**
    //  * Get the attachments for the message.
    //  *
    //  * @return array<int, Attachment>
    //  */
    // public function attachments(): array
    // {
    //     return [];
    // }
}

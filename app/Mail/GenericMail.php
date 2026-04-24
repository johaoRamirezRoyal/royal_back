<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $titulo;
    public string $contenido;

    public function __construct(string $titulo, string $contenido)
    {
        $this->titulo = $titulo;
        $this->contenido = $contenido;
    }

    public function build(){
        return $this->subject($this->titulo)
                    ->view('emails.generic');
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

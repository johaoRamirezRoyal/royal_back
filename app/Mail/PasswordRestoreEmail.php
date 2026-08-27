<?php

namespace App\Mail;

use App\Models\Usuarios\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordRestoreEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $url;

    /**
     * Create a new message instance.
     */
    public function __construct(Usuario $user, string $token)
    {
        $this->name = $user->nombre;
        $this->email = $user->correo;
        $this->url = config('app.frontend_url') . "/password/restore?" . http_build_query([
            'token' => $token,
            'email' => $this->email
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperacion de contraseña | OMNIA',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.passwordResotre',
            with: [
                'expires' => config('auth.passwords.users.expire'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

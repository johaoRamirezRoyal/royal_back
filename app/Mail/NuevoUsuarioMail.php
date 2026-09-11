<?php

namespace App\Mail;

use App\Models\Usuarios\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NuevoUsuarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $correo;
    public string $usuario;
    public string $url;

    /**
     * $pass viaja en texto plano: se arma justo tras crear el usuario, antes de que
     * se pierda la referencia (el modelo solo guarda el hash vía setPassAttribute).
     */
    public function __construct(Usuario $user, public string $pass)
    {
        $this->nombre = $user->nombre;
        $this->correo = $user->correo;
        $this->usuario = $user->user;
        $this->url = config('app.frontend_url') . '/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu cuenta ha sido creada | OMNIA',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.nuevoUsuario',
        );
    }
}

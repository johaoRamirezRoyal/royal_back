<?php

namespace App\Mail;

use App\Models\Branding\ColegioAdmision;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de acceso al portal para padres (AcudientesService::enviarAccesoPortal). Según
 * `$contrasena`: `documento` (entra con su documento y se le pide cambiarlo), `sin_contrasena`
 * (debe crearla) o `propia` (ya tiene; el botón sirve si la olvidó). Lleva la marca del colegio:
 * nombre, logo, color e imagen de fondo; sin marca activa, la de OMNIA.
 */
class AccesoPortalPadresMail extends Mailable
{
    public string $colegio;

    public ?string $logo;

    public ?string $imagen;

    public string $color;

    public function __construct(
        public string $nombre,
        public string $correo,
        public string $urlCrear,
        public string $urlPortal,
        public int $diasVigencia,
        ColegioAdmision $colegioAdmision,
        public string $contrasena = 'sin_contrasena',
    ) {
        $marca = $colegioAdmision->marca?->activo ? $colegioAdmision->marca : null;

        $this->colegio = $marca?->nombre ?: 'OMNIA';
        $this->logo = $marca?->logo_path;
        $this->imagen = $marca?->imagen_fondo_path;
        // Solo un hex válido entra al estilo en línea del correo.
        $this->color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $marca?->color) ? $marca->color : '#0b1530';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tu acceso al portal para padres de {$this->colegio}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.accesoPortalPadres');
    }
}

<?php

namespace App\Mail\Instituciones;

use App\Models\Instituciones\Institucion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio a una institución asociada para que diligencie y envíe la carta de
 * recomendación de sus egresados aspirantes desde su portal. Lo dispara manualmente
 * quien gestiona Instituciones (InstitucionAdminController::enviarRecordatorio).
 */
class RecordatorioDocumentoInstitucionMail extends Mailable
{
    use Queueable, SerializesModels;

    private const DOCUMENTO_POR_TIPO = [
        'coordinador_psicologo' => 'Carta de recomendación (formato Coordinador / Psicólogo)',
        'play_and_learn' => 'Carta de recomendación (formato Play and Learn)',
    ];

    public function __construct(
        public readonly Institucion $institucion,
        public readonly int $documentosEnviados,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: carta de recomendación pendiente — Admisiones ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorioDocumentoInstitucion',
            with: [
                'nombreInstitucion' => $this->institucion->nombre,
                'documento' => self::DOCUMENTO_POR_TIPO[$this->institucion->tipo_documento] ?? 'Carta de recomendación',
                'documentosEnviados' => $this->documentosEnviados,
                'urlPortal' => rtrim((string) config('app.frontend_url'), '/') . '/admissions?tipo=institucion',
            ],
        );
    }
}

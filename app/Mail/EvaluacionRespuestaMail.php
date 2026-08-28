<?php

namespace App\Mail;

use App\Models\Evaluaciones\EvaluacionRespuestaEvaluacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EvaluacionRespuestaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EvaluacionRespuestaEvaluacion $respuesta,
        public readonly string $pdfContenido,
        public readonly string $pdfNombre,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumen de evaluación de desempeño — ' . ($this->respuesta->evaluacion?->titulo ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.evaluacionRespuesta',
            with: [
                'nombreEvaluado' => trim(($this->respuesta->evaluado?->nombre ?? '') . ' ' . ($this->respuesta->evaluado?->apellido ?? '')),
                'tituloEvaluacion' => $this->respuesta->evaluacion?->titulo ?? '',
                'periodo' => $this->respuesta->periodo,
            ],
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

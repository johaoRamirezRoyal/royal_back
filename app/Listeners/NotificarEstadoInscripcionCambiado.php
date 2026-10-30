<?php

namespace App\Listeners;

use App\Enums\Mails;
use App\Events\EstadoInscripcionCambiado;
use App\Models\Usuarios\Usuario;
use App\Services\Admisiones\AdmisionesServices;
use App\Services\MailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Correos de un cambio de estado: a ADMISIONES_ESTADO y al acudiente siempre, y a las
 * psicólogas del nivel del aspirante cuando pasa a revisión de psicología. En la cola
 * `emails`, como RequestEmailAdmission: el cambio de estado ya no espera al correo.
 */
class NotificarEstadoInscripcionCambiado implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private MailService $mailService) {}

    public function handle(EstadoInscripcionCambiado $event): void
    {
        $mailTo = collect($this->destinatariosDelEstado())
            ->push($event->correoAcudiente)
            ->filter()
            ->values()
            ->toArray();

        if ($mailTo !== []) {
            $this->mailService->sendGeneric(
                $mailTo,
                "Actualización de estado de inscripción | {$event->codigo}",
                "
            Hola,

            Se ha actualizado el estado de la inscripción con código {$event->codigo}.

            Estado anterior: {$event->estadoAnterior}
            Nuevo estado: {$event->estadoNuevo}

            Aspirante: {$event->aspiranteNombre}

            Fecha de actualización: {$event->fecha}"
            );
        }

        if (! AdmisionesServices::esEstadoRevisionPsicologia($event->estadoNuevo)) {
            return;
        }

        $perfilPsicologa = AdmisionesServices::perfilPsicologaParaGrado($event->gradoAplica);
        $correosPsicologas = $perfilPsicologa ? $this->correosDelPerfil($perfilPsicologa) : [];

        if ($correosPsicologas === []) {
            Log::warning('No se encontró psicóloga para notificar el cambio de estado', [
                'id_inscripcion' => $event->idInscripcion,
                'grado_aplica' => $event->gradoAplica,
            ]);

            return;
        }

        $this->mailService->sendGeneric(
            $correosPsicologas,
            "Nueva inscripción en revisión de psicología | {$event->codigo}",
            "Se ha asignado la inscripción con código {$event->codigo} para revisión de psicología.\n\n".
            "Aspirante: {$event->aspiranteNombre}\n".
            "Grado al que aplica: {$event->gradoAplica}"
        );
    }

    /** @return array<int, string> */
    protected function destinatariosDelEstado(): array
    {
        return Mails::ADMISIONES_ESTADO->recipients();
    }

    /** @return array<int, string> */
    protected function correosDelPerfil(int $perfil): array
    {
        return Usuario::where('perfil', $perfil)->pluck('correo')->filter()->values()->all();
    }

    public function failed(EstadoInscripcionCambiado $event, \Throwable $e): void
    {
        Log::error('Falló la notificación del cambio de estado de inscripción', [
            'id_inscripcion' => $event->idInscripcion,
            'error' => $e->getMessage(),
        ]);
    }
}

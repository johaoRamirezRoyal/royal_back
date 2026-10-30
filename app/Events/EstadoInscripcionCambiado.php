<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El estado de una inscripción de admisiones cambió. Se despacha desde el único punto que lo
 * cambia: AdmisionesServices::actualizarEstadoDeInscripcionAspirante (Reportes, modo admin,
 * "Enviar admisión" del acudiente y la cita de psicología marcada como atendida).
 *
 * Lleva datos simples, no modelos, para que el listener en cola no dependa de volver a
 * leerlos: lo que se notifica es el cambio tal como ocurrió.
 */
class EstadoInscripcionCambiado
{
    use Dispatchable;

    public function __construct(
        public int $idInscripcion,
        public string $codigo,
        public ?string $estadoAnterior,
        public ?string $estadoNuevo,
        public ?string $aspiranteNombre,
        public ?string $gradoAplica,
        public ?string $correoAcudiente,
        public string $fecha,
    ) {}
}

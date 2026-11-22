<?php

namespace App\Enums\Cafeteria;

/** Estado de una asignación de personal. FINALIZADA: terminó su vigencia; REVOCADA: se retiró antes. */
enum EstadoAsignacion: string
{
    case ACTIVA = 'ACTIVA';
    case FINALIZADA = 'FINALIZADA';
    case REVOCADA = 'REVOCADA';
}

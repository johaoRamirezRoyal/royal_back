<?php

namespace App\Enums\Cafeteria;

/** Clase de punto de atención (el modelo deja `tipo` libre; aquí es un catálogo cerrado). */
enum TipoPuntoVenta: string
{
    case MOSTRADOR = 'MOSTRADOR';
    case AUTOSERVICIO = 'AUTOSERVICIO';
    case KIOSCO = 'KIOSCO';
    case MOVIL = 'MOVIL';
}

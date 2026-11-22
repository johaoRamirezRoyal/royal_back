<?php

namespace App\Enums\Cafeteria;

/** Estado de un punto de venta o de una terminal. */
enum EstadoPuntoVenta: string
{
    case ACTIVO = 'ACTIVO';
    case INACTIVO = 'INACTIVO';
    case MANTENIMIENTO = 'MANTENIMIENTO';
}

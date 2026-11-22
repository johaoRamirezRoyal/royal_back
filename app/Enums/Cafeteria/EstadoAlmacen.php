<?php

namespace App\Enums\Cafeteria;

/** Estado de un almacén. BLOQUEADO: existe pero no recibe ni despacha (p. ej. durante un conteo). */
enum EstadoAlmacen: string
{
    case ACTIVO = 'ACTIVO';
    case INACTIVO = 'INACTIVO';
    case BLOQUEADO = 'BLOQUEADO';
}

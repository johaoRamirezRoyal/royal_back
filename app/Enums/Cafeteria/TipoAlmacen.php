<?php

namespace App\Enums\Cafeteria;

/** Clase de almacén (el modelo deja `tipo` libre; aquí es un catálogo cerrado). */
enum TipoAlmacen: string
{
    case GENERAL = 'GENERAL';
    case REFRIGERADO = 'REFRIGERADO';
    case CONGELADO = 'CONGELADO';
    case COCINA = 'COCINA';
}

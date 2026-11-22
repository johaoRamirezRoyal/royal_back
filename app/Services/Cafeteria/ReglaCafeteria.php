<?php

namespace App\Services\Cafeteria;

use RuntimeException;

/**
 * Regla de negocio de la cafetería que no se cumple (p. ej. editar una cafetería cerrada). Los
 * services la lanzan dentro de `operacion()` y sale con su `status` (422; 404 si no existe).
 */
class ReglaCafeteria extends RuntimeException
{
    public function __construct(string $mensaje, public readonly int $status = 422)
    {
        parent::__construct($mensaje);
    }

    public static function noExiste(string $mensaje): self
    {
        return new self($mensaje, 404);
    }
}

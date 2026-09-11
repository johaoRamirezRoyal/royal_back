<?php

namespace App\Enums;

use App\Models\CorreoInstitucional;

enum Mails
{
    case ADMISIONES;
    case BIBLIOTECA;
    case GESTION_HUMANA;
    case DIRECCION_ADMINISTRATIVA;

    /**
     * Correos activos del grupo (tabla `correos_institucionales`, columna `grupo` = nombre
     * del case). Puede haber cero, uno o varios por grupo.
     */
    public function recipients(): array
    {
        return CorreoInstitucional::where('grupo', $this->name)
            ->where('activo', true)
            ->pluck('correo')
            ->all();
    }
}

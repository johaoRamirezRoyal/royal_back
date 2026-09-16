<?php

namespace App\Enums;

use App\Models\CorreoInstitucional;

enum Mails
{
    case ADMISIONES;
    case BIBLIOTECA;
    case GESTION_HUMANA;
    case DIRECCION_ADMINISTRATIVA;

    // Listas de distribución de Noticias (un envío por grupo reparte el correo del lado
    // del proveedor — ver NoticiasService::resolverDestinatariosDistribucion). Distintas
    // de las de arriba: cada BD/institución las carga con sus propias direcciones reales
    // en vez de traerlas hardcodeadas en el código.
    case NOTICIAS_TODOS;
    case NOTICIAS_PREESCOLAR;
    case NOTICIAS_PRIMARIA;
    case NOTICIAS_SECUNDARIA;
    case NOTICIAS_ADMINISTRATIVO;

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

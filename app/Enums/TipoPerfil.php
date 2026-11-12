<?php

namespace App\Enums;

/**
 * Qué clase de persona agrupa un perfil (perfiles.tipo). Los números de perfil cambian de un
 * colegio a otro; el tipo no: el código pregunta por el tipo, nunca por el id del perfil
 * (salvo Super Admin = 1). Cada colegio lo elige en /permisos.
 */
enum TipoPerfil: string
{
    case ADMINISTRATIVO = 'ADMINISTRATIVO';
    case DOCENTE = 'DOCENTE';
    case OPERATIVO = 'OPERATIVO';
    case ESTUDIANTE = 'ESTUDIANTE';
    case ACUDIENTE = 'ACUDIENTE';
    case PROVEEDOR = 'PROVEEDOR';
    case EGRESADO = 'EGRESADO';
}

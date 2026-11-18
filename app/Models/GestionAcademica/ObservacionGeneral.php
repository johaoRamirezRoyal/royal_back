<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** "Observaciones Generales del Director de Grupo" de un estudiante en un periodo. */
class ObservacionGeneral extends Model
{
    protected $table = 'academico_observacion_general';

    protected $fillable = ['id_estudiante', 'id_periodo_academico', 'id_curso', 'observacion', 'id_docente'];
}

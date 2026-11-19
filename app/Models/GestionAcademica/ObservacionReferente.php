<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Observación interna del docente por estudiante y referente: no sale en el boletín. */
class ObservacionReferente extends Model
{
    protected $table = 'academico_observacion_referente';

    protected $fillable = ['id_estudiante', 'id_referente', 'id_periodo_academico', 'id_curso', 'observacion', 'id_docente'];
}

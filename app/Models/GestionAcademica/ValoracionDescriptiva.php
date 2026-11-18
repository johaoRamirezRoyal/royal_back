<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Texto de un estudiante en un campo de un propósito, en un periodo (evaluación descriptiva). */
class ValoracionDescriptiva extends Model
{
    protected $table = 'academico_valoracion_descriptiva';

    protected $fillable = ['id_estudiante', 'id_campo', 'id_periodo_academico', 'id_curso', 'texto', 'id_docente'];
}

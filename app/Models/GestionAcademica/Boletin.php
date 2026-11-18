<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Foto del boletín de un estudiante en un periodo, tomada al cerrar el curso (ver NotasService::fotoBoletin). */
class Boletin extends Model
{
    protected $table = 'academico_boletin';

    protected $fillable = ['id_cierre', 'id_estudiante', 'id_periodo_academico', 'id_curso', 'datos'];

    protected $casts = ['datos' => 'array'];
}

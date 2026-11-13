<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Referente (indicador) de un grado en un periodo, en un propósito y una asignatura. Con
 * `id_padre` es un subreferente: hereda grado, periodo, propósito y asignatura del padre, se
 * califica igual y no sale en el boletín.
 */
class Referente extends Model
{
    protected $table = 'academico_referente';

    protected $fillable = ['id_grado', 'id_periodo_academico', 'id_proposito', 'id_asignatura', 'id_padre', 'descripcion', 'orden', 'id_docente'];

    protected $casts = ['orden' => 'integer', 'id_proposito' => 'integer', 'id_asignatura' => 'integer', 'id_padre' => 'integer'];
}

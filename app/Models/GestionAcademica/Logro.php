<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Indicador de desempeño (cualitativo) de un grado en un periodo, dentro de una de las dimensiones del grado. */
class Logro extends Model
{
    protected $table = 'academico_logro';

    protected $fillable = ['id_grado', 'id_periodo_academico', 'id_dimension', 'descripcion', 'orden', 'id_docente'];

    protected $casts = ['orden' => 'integer', 'id_dimension' => 'integer'];
}

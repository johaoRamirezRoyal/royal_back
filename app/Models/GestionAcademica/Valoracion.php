<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Desempeño final y observación de un estudiante en una dimensión y periodo académico.
 * id_curso es la foto del curso al calificar (ver la migración de notas cualitativas).
 */
class Valoracion extends Model
{
    protected $table = 'academico_valoracion';

    protected $fillable = ['id_estudiante', 'id_dimension', 'id_periodo_academico', 'id_curso', 'id_escala', 'observacion', 'id_docente'];

    public function logros()
    {
        return $this->hasMany(ValoracionLogro::class, 'id_valoracion');
    }
}

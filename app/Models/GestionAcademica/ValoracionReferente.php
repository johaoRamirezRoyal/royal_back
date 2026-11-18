<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Desempeño de un estudiante en un referente o subreferente. El docente pone `id_escala_docente`;
 * en un referente, el coordinador puede ajustarlo (`id_escala_ajuste`). El final es el ajuste o,
 * si no hay, el del docente. id_curso es la foto del curso al calificar.
 */
class ValoracionReferente extends Model
{
    protected $table = 'academico_valoracion_referente';

    protected $fillable = [
        'id_estudiante', 'id_referente', 'id_periodo_academico', 'id_curso',
        'id_escala_docente', 'id_docente', 'id_escala_ajuste', 'id_ajustado_por', 'ajustado_at',
    ];

    protected $casts = ['ajustado_at' => 'datetime'];
}

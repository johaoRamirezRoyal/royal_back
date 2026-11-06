<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Dimensión de evaluación cualitativa (cognitiva, comunicativa, corporal...). Cada grado elige las
 * suyas (academico_grado_dimension) y cada logro pertenece a una de las de su grado. No se borran:
 * se desactivan.
 */
class Dimension extends Model
{
    protected $table = 'academico_dimension';

    protected $fillable = ['nombre', 'descripcion', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];
}

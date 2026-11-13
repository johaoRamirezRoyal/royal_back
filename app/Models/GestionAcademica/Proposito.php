<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Propósito de la evaluación cualitativa: catálogo único del colegio, el mismo para todos los
 * grados y docentes. Agrupa los referentes. No se borran: se desactivan.
 */
class Proposito extends Model
{
    protected $table = 'academico_proposito';

    protected $fillable = ['nombre', 'descripcion', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];
}

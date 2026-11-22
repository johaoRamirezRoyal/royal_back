<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;

/** Unidad de medida. El código sale de la abreviatura (KG, ML) y no cambia. */
class UnidadMedida extends Model
{
    protected $table = 'caf_unidades_medida';

    protected $fillable = ['codigo', 'nombre', 'abreviatura', 'precision_decimal', 'activo'];

    protected $casts = ['precision_decimal' => 'integer', 'activo' => 'boolean'];
}

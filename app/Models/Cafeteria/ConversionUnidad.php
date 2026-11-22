<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 unidad de origen = `factor_conversion` unidades de destino. */
class ConversionUnidad extends Model
{
    protected $table = 'caf_conversiones_unidades';

    protected $fillable = ['id_unidad_origen', 'id_unidad_destino', 'factor_conversion', 'activa'];

    protected $casts = [
        'id_unidad_origen' => 'integer', 'id_unidad_destino' => 'integer',
        'factor_conversion' => 'decimal:6', 'activa' => 'boolean',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_origen');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_destino');
    }
}

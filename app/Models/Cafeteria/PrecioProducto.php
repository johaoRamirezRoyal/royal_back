<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Precio con vigencia. `id_punto_venta` NULL = general; uno por punto lo reemplaza en ese punto. */
class PrecioProducto extends Model
{
    protected $table = 'caf_precios_productos';

    protected $fillable = ['id_producto', 'id_punto_venta', 'precio', 'vigencia_desde', 'vigencia_hasta', 'creado_por'];

    protected $casts = [
        'id_producto' => 'integer', 'id_punto_venta' => 'integer', 'precio' => 'decimal:2',
        'vigencia_desde' => 'date:Y-m-d', 'vigencia_hasta' => 'date:Y-m-d', 'creado_por' => 'integer',
    ];

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'id_punto_venta');
    }

    /** Precios del mismo producto y del mismo alcance (el general, o los de ese punto). */
    public function scopeMismoAlcance(Builder $q, int $idProducto, ?int $idPunto): Builder
    {
        return $q->where('id_producto', $idProducto)->when(
            $idPunto === null,
            fn ($w) => $w->whereNull('id_punto_venta'),
            fn ($w) => $w->where('id_punto_venta', $idPunto),
        );
    }
}

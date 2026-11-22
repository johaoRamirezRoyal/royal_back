<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Producto que ofrece un proveedor de Compras (`id_proveedor` = usuarios.id_user). */
class ProveedorProducto extends Model
{
    protected $table = 'caf_proveedor_productos';

    protected $fillable = [
        'id_proveedor', 'id_producto', 'codigo_producto_proveedor', 'precio_referencia',
        'cantidad_minima', 'es_preferido', 'activo',
    ];

    protected $casts = [
        'id_proveedor' => 'integer', 'id_producto' => 'integer', 'precio_referencia' => 'decimal:2',
        'cantidad_minima' => 'decimal:3', 'es_preferido' => 'boolean', 'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}

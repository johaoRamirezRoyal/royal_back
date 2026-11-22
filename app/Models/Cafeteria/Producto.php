<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\TipoProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Producto del catálogo. `codigo` es el SKU (PRD-0001), lo asigna el sistema. */
class Producto extends Model
{
    protected $table = 'caf_productos';

    protected $fillable = [
        'id_categoria', 'id_unidad_base', 'codigo', 'codigo_barras', 'nombre', 'descripcion',
        'tipo_producto', 'controla_inventario', 'es_vendible', 'activo', 'creado_por',
    ];

    protected $casts = [
        'id_categoria' => 'integer', 'id_unidad_base' => 'integer', 'tipo_producto' => TipoProducto::class,
        'controla_inventario' => 'boolean', 'es_vendible' => 'boolean', 'activo' => 'boolean', 'creado_por' => 'integer',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'id_categoria');
    }

    public function unidadBase(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_base');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioProducto::class, 'id_producto');
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(ProveedorProducto::class, 'id_producto');
    }
}

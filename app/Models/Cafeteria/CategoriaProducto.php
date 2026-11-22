<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoría del catálogo (jerárquica: `id_categoria_padre`). Código CAT-001, del sistema. */
class CategoriaProducto extends Model
{
    protected $table = 'caf_categorias_productos';

    protected $fillable = ['id_categoria_padre', 'codigo', 'nombre', 'descripcion', 'activo'];

    protected $casts = ['id_categoria_padre' => 'integer', 'activo' => 'boolean'];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_categoria_padre');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'id_categoria_padre');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria');
    }
}

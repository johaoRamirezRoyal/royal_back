<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de valores de `categoria.tipo_categoria` (1=Sistemas,
 * 2=Operativo, 3=Área Común) — no hay columna `id_subcategoria` en
 * `categoria`, la relación es directa sobre `tipo_categoria` (ver
 * migración 2026_09_10_200000_recreate_subcategoria_inventario_related_to_tipo_categoria).
 */
class SubcategoriaInventario extends Model
{
    protected $table = "subcategoria_inventario";

    protected $fillable = [
        'nombre',
        'activo',
    ];

    public $timestamps = false;
}

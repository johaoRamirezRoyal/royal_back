<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;

/** Franja de atención de un día (`id_dia_semana`: 1 = lunes … 7 = domingo, catálogo `dias_semana`). */
class HorarioPuntoVenta extends Model
{
    protected $table = 'caf_horarios_punto_venta';

    public $timestamps = false;

    protected $fillable = ['id_punto_venta', 'id_dia_semana', 'hora_apertura', 'hora_cierre', 'activo'];

    protected $casts = ['id_punto_venta' => 'integer', 'id_dia_semana' => 'integer', 'activo' => 'boolean'];
}

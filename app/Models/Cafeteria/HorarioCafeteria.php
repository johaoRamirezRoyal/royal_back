<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;

/** Franja de atención de un día (`id_dia_semana`: 1 = lunes … 7 = domingo, catálogo `dias_semana`). */
class HorarioCafeteria extends Model
{
    protected $table = 'caf_horarios_cafeteria';

    public $timestamps = false;

    protected $fillable = ['id_cafeteria', 'id_dia_semana', 'hora_apertura', 'hora_cierre', 'activo'];

    protected $casts = ['id_cafeteria' => 'integer', 'id_dia_semana' => 'integer', 'activo' => 'boolean'];
}

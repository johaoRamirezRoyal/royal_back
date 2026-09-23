<?php

namespace App\Models\Capacitaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tabla legacy `capacitacion_preguntas` — `id_capacitacion` = capacitacion_curso.id. */
class CapPregunta extends Model
{
    protected $table = 'capacitacion_preguntas';

    public $timestamps = false;

    protected $fillable = ['enunciado', 'id_capacitacion', 'id_log'];

    protected $casts = [
        'id_capacitacion' => 'integer',
        'id_log' => 'integer',
    ];

    public function opciones(): HasMany
    {
        return $this->hasMany(CapOpcion::class, 'id_pregunta')->orderBy('id');
    }
}

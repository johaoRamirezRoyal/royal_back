<?php

namespace App\Models\Capacitaciones;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla legacy `capacitacion_opciones`. `esCorrecto` va oculto por defecto para no filtrar
 * la respuesta al usuario que presenta la prueba; la administración lo hace visible.
 */
class CapOpcion extends Model
{
    protected $table = 'capacitacion_opciones';

    public $timestamps = false;

    protected $fillable = ['id_pregunta', 'enunciado', 'esCorrecto', 'id_log'];

    protected $hidden = ['esCorrecto'];

    protected $casts = [
        'id_pregunta' => 'integer',
        'esCorrecto' => 'boolean',
        'id_log' => 'integer',
    ];
}

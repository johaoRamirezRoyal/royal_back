<?php

namespace App\Models\AsistenciaGestion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionAsistencia extends Model
{
    protected $table = 'configuracion_asistencia';

    public $timestamps = false;

    protected $fillable = [
        'hora_minima_salida_defecto',
        'notificar_llegada_tarde',
        'notificar_llegada_tarde_trabajador',
        'notificar_recursos_humanos',
        'notificar_coordinador_nivel',
    ];

    protected $casts = [
        'notificar_llegada_tarde' => 'boolean',
        'notificar_llegada_tarde_trabajador' => 'boolean',
        'notificar_recursos_humanos' => 'boolean',
        'notificar_coordinador_nivel' => 'boolean',
    ];
}

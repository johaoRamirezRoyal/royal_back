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
        'perfiles_notificar_llegada_tarde',
    ];

    protected $casts = [
        'notificar_llegada_tarde' => 'boolean',
        'notificar_llegada_tarde_trabajador' => 'boolean',
        'perfiles_notificar_llegada_tarde' => 'array',
    ];
}

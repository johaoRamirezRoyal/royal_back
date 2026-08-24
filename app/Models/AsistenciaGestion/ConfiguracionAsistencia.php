<?php

namespace App\Models\AsistenciaGestion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionAsistencia extends Model
{
    protected $table = 'configuracion_asistencia';

    public $timestamps = false;

    protected $fillable = [
        'hora_minima_salida_defecto',
    ];
}

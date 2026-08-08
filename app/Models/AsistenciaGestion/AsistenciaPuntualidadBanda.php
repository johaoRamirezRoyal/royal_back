<?php

namespace App\Models\AsistenciaGestion;

use Illuminate\Database\Eloquent\Model;

class AsistenciaPuntualidadBanda extends Model
{
    protected $table = 'asistencia_puntualidad_bandas';

    public $timestamps = false;

    protected $fillable = [
        'id_horario',
        'nombre',
        'hora_desde',
        'hora_hasta',
        'orden',
    ];

    public function horario()
    {
        return $this->belongsTo(AsistenciaHorario::class, 'id_horario');
    }
}

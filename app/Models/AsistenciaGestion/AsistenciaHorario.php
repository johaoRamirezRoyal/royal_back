<?php

namespace App\Models\AsistenciaGestion;

use Illuminate\Database\Eloquent\Model;

class AsistenciaHorario extends Model
{
    protected $table = 'asistencia_horarios_estandar';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'grupo_id',
        'hora_llegada_esperada',
        'hora_salida_esperada',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function bandas()
    {
        return $this->hasMany(AsistenciaPuntualidadBanda::class, 'id_horario')->orderBy('orden');
    }
}

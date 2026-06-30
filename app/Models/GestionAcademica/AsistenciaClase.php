<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

class AsistenciaClase extends Model
{
    protected $table = 'academico_asistencia_clase';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_horario_clase',
        'fecha',
        'estado',
        'observacion',
    ];

    public function horarioClase()
    {
        return $this->belongsTo(HorarioClase::class, 'id_horario_clase', 'id');
    }
}

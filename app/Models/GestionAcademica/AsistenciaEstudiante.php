<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class AsistenciaEstudiante extends Model
{
    protected $table = 'academico_asistencia_estudiante';

    protected $primaryKey = 'id';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_asistencia_clase',
        'id_alumno',
        'estado',
        'observacion',
    ];

    public function asistenciaClase()
    {
        return $this->belongsTo(AsistenciaClase::class, 'id_asistencia_clase', 'id');
    }

    public function alumno()
    {
        return $this->belongsTo(Usuario::class, 'id_alumno', 'id_user');
    }
}

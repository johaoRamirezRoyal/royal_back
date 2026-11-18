<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Inasistencias / llegadas tarde del boletín escritas a mano (solo si no hay registros). */
class AsistenciaManual extends Model
{
    protected $table = 'academico_asistencia_manual';

    protected $fillable = ['id_estudiante', 'id_periodo_academico', 'id_curso', 'inasistencias', 'llegadas_tarde', 'id_usuario'];

    protected $casts = ['inasistencias' => 'integer', 'llegadas_tarde' => 'integer'];
}

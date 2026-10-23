<?php

namespace App\Models\AsistenciaGestion;

use Illuminate\Database\Eloquent\Model;

/** Marcación del dispositivo posterior a la entrada del día (ver la migración de la tabla). */
class AsistenciaGestionMarcacion extends Model
{
    protected $table = 'asistencia_gestion_marcaciones';
    public $timestamps = false;

    protected $fillable = ['id_asistencia', 'hora'];
}

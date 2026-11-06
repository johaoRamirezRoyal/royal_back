<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

/** Quién hizo qué en un plan de logros (cambios de logros y de estado). */
class LogroPlanHistorial extends Model
{
    protected $table = 'academico_logro_plan_historial';

    public $timestamps = false;

    protected $fillable = ['id_plan', 'id_usuario', 'accion', 'detalle', 'fecha'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_user');
    }
}

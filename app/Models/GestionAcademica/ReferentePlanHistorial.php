<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

/** Quién hizo qué en un plan de referentes (cambios de referentes y de estado). */
class ReferentePlanHistorial extends Model
{
    protected $table = 'academico_referente_plan_historial';

    public $timestamps = false;

    protected $fillable = ['id_plan', 'id_usuario', 'accion', 'detalle', 'fecha'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_user');
    }
}

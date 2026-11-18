<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

/** Una asignatura cuyos referentes ya se enviaron a revisión dentro del plan del grado. */
class ReferenteEnvio extends Model
{
    protected $table = 'academico_referente_envio';

    public $timestamps = false;

    protected $fillable = ['id_plan', 'id_asignatura', 'id_usuario', 'fecha'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_user');
    }
}

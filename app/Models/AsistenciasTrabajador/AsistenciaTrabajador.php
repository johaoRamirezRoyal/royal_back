<?php

namespace App\Models\AsistenciasTrabajador;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class AsistenciaTrabajador extends Model
{
    public $timestamps = false;

    protected $table = 'asistencia_gestion';

    protected $fillable = [
        'id_user',
        'fecha_asistencia',
        'hora_asistencia',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

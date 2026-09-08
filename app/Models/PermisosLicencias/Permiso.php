<?php

namespace App\Models\PermisosLicencias;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permiso';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'tipo_permiso',
        'motivo_permiso',
        'fecha_permiso',
        'fecha_retorno',
        'dias_permiso',
        'hora_salida',
        'tiempo_permiso',
        'descripcion',
        'estado',
        'activo',
        'id_log',
        'fecha_edit',
        'id_edit',
        'motivo_rechazo',
        'evidencia_permiso',
        'remunerado',
        'tipo_permiso_detalle',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'tipo_permiso' => 'integer',
        'motivo_permiso' => 'integer',
        'estado' => 'integer',
        'activo' => 'integer',
        'id_log' => 'integer',
        'id_edit' => 'integer',
        'fecha_permiso' => 'date',
        'fecha_retorno' => 'date',
        'fechareg' => 'datetime',
        'fecha_edit' => 'datetime',
    ];

    protected $attributes = [
        'estado' => 0,
        'activo' => 1,
        'evidencia_permiso' => '',
        'tipo_permiso_detalle' => '',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function tipo()
    {
        return $this->belongsTo(PermisoTipo::class, 'tipo_permiso', 'id');
    }

    public function motivo()
    {
        return $this->belongsTo(PermisoMotivo::class, 'motivo_permiso', 'id');
    }
}

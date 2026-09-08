<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

/** 1 = Permiso Parcial, 2 = Día Completo (ver legacy vistas/ajax/recursos/mostrarFormularioTipoPermiso.php) — catálogo, IDs asumidos ya sembrados. */
class PermisoTipo extends Model
{
    protected $table = 'permiso_tipo';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nombre', 'id_log', 'activo'];

    protected $casts = [
        'id_log' => 'integer',
        'activo' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = ['activo' => 1];
}

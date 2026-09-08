<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

class PermisoInstitucional extends Model
{
    protected $table = 'permisos_institucionales';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nombre_permiso', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    protected $attributes = ['activo' => true];
}

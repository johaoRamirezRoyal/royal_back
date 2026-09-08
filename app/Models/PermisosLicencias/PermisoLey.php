<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

class PermisoLey extends Model
{
    protected $table = 'permiso_ley';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nombre_permiso', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    protected $attributes = ['activo' => true];
}

<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

/** Catálogo general de motivos — algunos IDs (1=Personal, 3=Ley, 6=Institucional, ver legacy) disparan un sub-catálogo adicional en el formulario. */
class PermisoMotivo extends Model
{
    protected $table = 'permiso_motivo';

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

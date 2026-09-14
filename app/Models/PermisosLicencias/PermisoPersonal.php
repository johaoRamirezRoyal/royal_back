<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

class PermisoPersonal extends Model
{
    protected $table = 'permiso_personal';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nombre_permiso', 'id_motivo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    protected $attributes = ['activo' => true];

    /** Ver PermisoLey::motivo(). */
    public function motivo()
    {
        return $this->belongsTo(PermisoMotivo::class, 'id_motivo', 'id');
    }
}

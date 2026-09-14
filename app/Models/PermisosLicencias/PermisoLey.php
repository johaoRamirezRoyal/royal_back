<?php

namespace App\Models\PermisosLicencias;

use Illuminate\Database\Eloquent\Model;

class PermisoLey extends Model
{
    protected $table = 'permiso_ley';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nombre_permiso', 'id_motivo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    protected $attributes = ['activo' => true];

    /** Motivo general al que pertenece este permiso legal puntual — ver migración
     *  2026_09_14_140000_add_id_motivo_to_permiso_catalogos. Sin FK real (MyISAM vs InnoDB). */
    public function motivo()
    {
        return $this->belongsTo(PermisoMotivo::class, 'id_motivo', 'id');
    }
}

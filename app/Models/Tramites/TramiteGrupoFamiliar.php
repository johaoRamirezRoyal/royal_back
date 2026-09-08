<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

/** Catálogo compartido de nombres — usado tanto para "grupo familiar EPS" (tipo 3) como "beneficiario" (tipo 4), igual que en el legacy. */
class TramiteGrupoFamiliar extends Model
{
    protected $table = 'tramite_grupo_familiar';

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

<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

class TramiteFamiliar extends Model
{
    protected $table = 'tramite_familiar';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['id_tramite', 'grupo_familiar', 'id_log'];

    protected $casts = [
        'id_tramite' => 'integer',
        'grupo_familiar' => 'integer',
        'id_log' => 'integer',
        'fechareg' => 'datetime',
    ];

    public function grupo()
    {
        return $this->belongsTo(TramiteGrupoFamiliar::class, 'grupo_familiar', 'id');
    }
}

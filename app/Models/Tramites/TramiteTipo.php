<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

class TramiteTipo extends Model
{
    protected $table = 'tramite_tipo';

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

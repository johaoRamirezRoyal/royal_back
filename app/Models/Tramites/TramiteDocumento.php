<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

class TramiteDocumento extends Model
{
    protected $table = 'tramite_documentos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['id_tramite', 'archivo', 'id_log'];

    protected $casts = [
        'id_tramite' => 'integer',
        'id_log' => 'integer',
        'fechareg' => 'datetime',
    ];
}

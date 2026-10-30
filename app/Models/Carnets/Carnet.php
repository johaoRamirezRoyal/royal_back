<?php

namespace App\Models\Carnets;

use Illuminate\Database\Eloquent\Model;

class Carnet extends Model
{
    protected $table = 'carnets';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'version',
        'estado',
        'fecha_emision',
        'fecha_entrega',
        'fecha_estado',
        'observacion',
        'id_usuario_emite',
        'id_usuario_estado',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'version' => 'integer',
        'fecha_emision' => 'datetime:Y-m-d H:i:s',
        'fecha_entrega' => 'datetime:Y-m-d H:i:s',
        'fecha_estado' => 'datetime:Y-m-d H:i:s',
    ];
}

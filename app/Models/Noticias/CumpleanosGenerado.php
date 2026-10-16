<?php

namespace App\Models\Noticias;

use Illuminate\Database\Eloquent\Model;

class CumpleanosGenerado extends Model
{
    protected $table = 'cumpleanos_generados';

    protected $fillable = [
        'id_user',
        'anio',
        'fecha',
        'id_plantilla',
        'id_mensaje',
        'estado',
        'error',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'anio' => 'integer',
        'id_user' => 'integer',
        'id_plantilla' => 'integer',
        'id_mensaje' => 'integer',
    ];
}

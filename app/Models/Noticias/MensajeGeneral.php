<?php

namespace App\Models\Noticias;

use Illuminate\Database\Eloquent\Model;

class MensajeGeneral extends Model
{
    protected $table = 'asistencia_mensaje_general';

    protected $fillable = [
        'titulo',
        'imagen',
        'mensaje',
        'id_log',
        'activo',
        'nivel',
        'ultimo_envio_fecha',
    ];

    public $timestamps = false;

    protected $casts = [
        'ultimo_envio_fecha' => 'date:Y-m-d',
    ];

    protected $attributes = [
        'activo' => 1,
    ];
}

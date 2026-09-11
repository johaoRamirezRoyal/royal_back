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
    ];

    public $timestamps = false;

    protected $attributes = [
        'activo' => 1,
    ];
}

<?php

namespace App\Models\Reservas;

class Reservas extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id';

    const CREATED_AT = "fechareg";

    protected $fillable = [
        'id',
        'id_salon',
        'portatil',
        'sonido',
        'fecha_reserva',
        'hora_reserva',
        'detalle_reserva',
        'id_user',
        'activo',
        'confirmado',
        'fecha_confirmado',
        'fechareg',
        'id_cancelado',
        'fecha_cancelado',
        'reserva_grupo',
    ];

    protected $casts = [
        'fechareg' => 'datetime',
        'fecha_confirmado' => 'datetime',
        'fecha_cancelado' => 'datetime',
    ];
}
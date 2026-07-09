<?php

namespace App\Models\Reservas;

use App\Models\Usuarios\Usuario;

class Reservas extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id';

    const CREATED_AT = "fechareg";
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'id_salon',
        'portatil',
        'sonido',
        'titulo',
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

    public function hora(){
        return $this->belongsTo(Horas::class, 'hora_reserva', 'id');
    }

    public function salon(){
        return $this->belongsTo(Salones::class, 'id_salon', 'id');
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}
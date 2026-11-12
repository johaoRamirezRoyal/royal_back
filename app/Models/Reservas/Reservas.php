<?php

namespace App\Models\Reservas;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Reservas extends Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = null;

    // Valores de `confirmado`. 2 es el default al crear (reserva activa).
    const CONFIRMADO_FINALIZADA = 1;

    const CONFIRMADO_RESERVADA = 2;

    const CONFIRMADO_PENDIENTE = 3;   // salón con responsable, esperando su autorización

    const CONFIRMADO_RECHAZADA = 4;   // el responsable la rechazó (además activo=0, fecha_cancelado)

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

    public function hora()
    {
        return $this->belongsTo(Horas::class, 'hora_reserva', 'id');
    }

    public function salon()
    {
        return $this->belongsTo(Salones::class, 'id_salon', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

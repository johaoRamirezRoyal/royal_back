<?php

namespace App\Models\Reservas;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Salones extends Model
{
    use HasFactory;

    protected $table = 'salones';

    protected $primaryKey = 'id';

    const CREATED_AT = "fechareg";
    const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'portatil',
        'sonido',
        'correo_notificacion',
        'id_user',
        'activo',
        'fechareg'
    ];

    protected $casts = [
        'fechareg' => 'datetime',
    ];

    public function Usuario()
    {
        return $this->hasMany(
            Usuario::class,
            'id_user',
            'id_user'
        );
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', 1);
    }

    /**
     * Correo(s) del encargado de este salón (separados por coma) — se suman a los
     * correos globales de ConfiguracionReservas al notificar una reserva nueva.
     */
    public function correosNotificacion(): array
    {
        if (! $this->correo_notificacion) return [];

        return array_filter(array_map(
            fn ($correo) => mb_strtolower(trim($correo)),
            explode(',', $this->correo_notificacion)
        ));
    }
}

<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\EstadoCafeteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cafeteria extends Model
{
    protected $table = 'caf_cafeterias';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'estado', 'creado_por', 'actualizado_por'];

    protected $casts = ['estado' => EstadoCafeteria::class];

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class, 'id_cafeteria');
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class, 'id_cafeteria');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioCafeteria::class, 'id_cafeteria');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPersonal::class, 'id_cafeteria');
    }
}

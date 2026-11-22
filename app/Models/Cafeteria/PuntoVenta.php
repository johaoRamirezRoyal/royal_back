<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\EstadoPuntoVenta;
use App\Enums\Cafeteria\TipoPuntoVenta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PuntoVenta extends Model
{
    protected $table = 'caf_puntos_venta';

    protected $fillable = ['id_cafeteria', 'codigo', 'nombre', 'tipo', 'ubicacion', 'estado'];

    protected $casts = ['id_cafeteria' => 'integer', 'tipo' => TipoPuntoVenta::class, 'estado' => EstadoPuntoVenta::class];

    public function cafeteria(): BelongsTo
    {
        return $this->belongsTo(Cafeteria::class, 'id_cafeteria');
    }

    public function terminales(): HasMany
    {
        return $this->hasMany(TerminalVenta::class, 'id_punto_venta');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioPuntoVenta::class, 'id_punto_venta');
    }

    /** Almacenes de los que se abastece; `pivot->es_predeterminado` marca el de siempre. */
    public function almacenes(): BelongsToMany
    {
        return $this->belongsToMany(Almacen::class, 'caf_puntos_venta_almacenes', 'id_punto_venta', 'id_almacen')
            ->withPivot('es_predeterminado');
    }
}

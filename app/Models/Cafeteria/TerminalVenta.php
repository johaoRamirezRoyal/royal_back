<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\EstadoPuntoVenta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalVenta extends Model
{
    protected $table = 'caf_terminales_venta';

    protected $fillable = ['id_punto_venta', 'codigo', 'nombre', 'identificador_dispositivo', 'estado'];

    protected $casts = ['id_punto_venta' => 'integer', 'estado' => EstadoPuntoVenta::class];

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'id_punto_venta');
    }
}

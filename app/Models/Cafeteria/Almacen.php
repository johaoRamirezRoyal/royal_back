<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\EstadoAlmacen;
use App\Enums\Cafeteria\TipoAlmacen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** `id_cafeteria` NULL = almacén central de la institución. */
class Almacen extends Model
{
    protected $table = 'caf_almacenes';

    protected $fillable = ['id_cafeteria', 'codigo', 'nombre', 'tipo', 'descripcion', 'estado', 'creado_por'];

    protected $casts = ['id_cafeteria' => 'integer', 'tipo' => TipoAlmacen::class, 'estado' => EstadoAlmacen::class];

    public function cafeteria(): BelongsTo
    {
        return $this->belongsTo(Cafeteria::class, 'id_cafeteria');
    }

    public function puntosVenta(): BelongsToMany
    {
        return $this->belongsToMany(PuntoVenta::class, 'caf_puntos_venta_almacenes', 'id_almacen', 'id_punto_venta')
            ->withPivot('es_predeterminado');
    }
}

<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionReservas extends Model
{
    public const ID_CONFIG = 1;

    protected $table = 'configuracion_reservas';

    public $timestamps = false;

    protected $fillable = [
        'dias_min_anticipacion',
        'dias_max_anticipacion',
    ];

    protected $casts = [
        'dias_min_anticipacion' => 'integer',
        'dias_max_anticipacion' => 'integer',
    ];

    /**
     * Fila singleton — `firstOrCreate` en vez de `findOrFail` porque el `id=1` insertado
     * por la migración de creación no llega en entornos donde esa migración ya estaba
     * marcada como ejecutada antes de agregarle el `insert` (no vuelve a correr).
     */
    public static function actual(): self
    {
        return self::firstOrCreate(['id' => self::ID_CONFIG]);
    }
}

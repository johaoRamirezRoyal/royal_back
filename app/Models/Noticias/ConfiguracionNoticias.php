<?php

namespace App\Models\Noticias;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionNoticias extends Model
{
    public const ID_CONFIG = 1;

    protected $table = 'configuracion_noticias';

    public $timestamps = false;

    protected $fillable = [
        'enviar_correos_programadas',
    ];

    protected $casts = [
        'enviar_correos_programadas' => 'boolean',
    ];

    protected $attributes = [
        'enviar_correos_programadas' => true,
    ];

    /** Fila singleton — `firstOrCreate` por la misma razón que ConfiguracionReservas::actual. */
    public static function actual(): self
    {
        return self::firstOrCreate(['id' => self::ID_CONFIG]);
    }
}

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
        'cumpleanos_automatico',
        'cumpleanos_plantilla_masculino',
        'cumpleanos_plantilla_femenino',
        'cumpleanos_dias_anticipacion',
        'cumpleanos_titulo',
    ];

    protected $casts = [
        'enviar_correos_programadas' => 'boolean',
        'cumpleanos_automatico' => 'boolean',
        'cumpleanos_plantilla_masculino' => 'integer',
        'cumpleanos_plantilla_femenino' => 'integer',
        'cumpleanos_dias_anticipacion' => 'integer',
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

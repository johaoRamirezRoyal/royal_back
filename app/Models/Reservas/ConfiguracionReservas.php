<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionReservas extends Model
{
    public const ID_CONFIG = 1;

    protected $table = 'configuracion_reservas';

    public $timestamps = false;

    protected $fillable = [
        'correo_notificacion',
        'dias_min_anticipacion',
        'dias_max_anticipacion',
    ];

    protected $casts = [
        'dias_min_anticipacion' => 'integer',
        'dias_max_anticipacion' => 'integer',
    ];

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }

    /**
     * Correos que reciben SIEMPRE una notificación al reservar cualquier salón (separados
     * por coma) — mismo formato que Salones::correosNotificacion() y
     * ConfiguracionInstituciones::correosNotificacion().
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

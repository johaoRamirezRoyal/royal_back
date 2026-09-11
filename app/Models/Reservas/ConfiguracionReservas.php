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

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }
}

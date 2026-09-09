<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerInformativo extends Model
{
    public const ID_CONFIG = 1;

    public const VARIANTES = ['info', 'warning', 'success', 'danger'];

    public const TAMANOS = ['sm', 'md', 'lg', 'xl'];

    protected $table = 'banner_informativo';

    public $timestamps = false;

    protected $fillable = [
        'mensaje',
        'variante',
        'tamano',
        'activo',
        'actualizado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }
}

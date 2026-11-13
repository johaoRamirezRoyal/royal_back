<?php

namespace App\Models\CursosAltura;

use Illuminate\Database\Eloquent\Model;

/** Catálogo de EPS, AFP y ARL (columna `tipo`). */
class EntidadSeguridadSocial extends Model
{
    public const TIPOS = ['EPS', 'AFP', 'ARL'];

    protected $table = 'entidades_seguridad_social';

    public $timestamps = false;

    protected $fillable = ['tipo', 'nombre', 'activo', 'user_log'];

    protected $hidden = ['user_log', 'fechareg'];

    protected function casts(): array
    {
        return ['activo' => 'integer'];
    }
}

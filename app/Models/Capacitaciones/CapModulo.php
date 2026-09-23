<?php

namespace App\Models\Capacitaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tabla legacy `capacitacion_modulos`. */
class CapModulo extends Model
{
    protected $table = 'capacitacion_modulos';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'id_curso', 'id_log'];

    protected $casts = [
        'id_curso' => 'integer',
        'id_log' => 'integer',
    ];

    // Mismo orden que el legado (por fecha de registro).
    public function contenidos(): HasMany
    {
        return $this->hasMany(CapContenido::class, 'id_modulo')->orderBy('fechareg')->orderBy('id');
    }
}

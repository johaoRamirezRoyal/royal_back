<?php

namespace App\Models\CursosAltura;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** EPS, AFP y ARL de un trabajador (una fila por trabajador), cada una con documento opcional. */
class SeguridadSocialTrabajador extends Model
{
    protected $table = 'seguridad_social_trabajador';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'id_eps', 'ruta_eps',
        'id_afp', 'ruta_afp',
        'id_arl', 'ruta_arl',
        'user_log',
    ];

    protected function casts(): array
    {
        return [
            'id_user' => 'integer',
            'id_eps' => 'integer',
            'id_afp' => 'integer',
            'id_arl' => 'integer',
        ];
    }

    public function eps(): BelongsTo
    {
        return $this->belongsTo(EntidadSeguridadSocial::class, 'id_eps');
    }

    public function afp(): BelongsTo
    {
        return $this->belongsTo(EntidadSeguridadSocial::class, 'id_afp');
    }

    public function arl(): BelongsTo
    {
        return $this->belongsTo(EntidadSeguridadSocial::class, 'id_arl');
    }
}

<?php

namespace App\Models\Capacitaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabla legacy `capacitaciones_contenido` — `contenido` es la URL que se embebe en un iframe. */
class CapContenido extends Model
{
    protected $table = 'capacitaciones_contenido';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'contenido', 'id_modulo', 'id_log'];

    protected $casts = [
        'id_modulo' => 'integer',
        'id_log' => 'integer',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(CapModulo::class, 'id_modulo');
    }
}

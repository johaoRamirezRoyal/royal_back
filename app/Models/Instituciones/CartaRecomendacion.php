<?php

namespace App\Models\Instituciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartaRecomendacion extends Model
{
    protected $table = 'cartas_recomendacion';

    protected $fillable = [
        'id_institucion',
        'idioma',
        'datos',
        'documento_url',
        'documento_public_id',
    ];

    protected $casts = [
        'datos' => 'array',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'id_institucion');
    }
}

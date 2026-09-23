<?php

namespace App\Models\Noticias;

use Illuminate\Database\Eloquent\Model;

class Revista extends Model
{
    protected $table = 'noticias_revistas';

    protected $fillable = [
        'titulo',
        'descripcion',
        'url',
        'public_id',
        'activo',
        'id_log',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}

<?php

namespace App\Models\Noticias;

use Illuminate\Database\Eloquent\Model;

class PlantillaCumpleanos extends Model
{
    protected $table = 'cumpleanos_plantillas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'genero',
        'activo',
        'imagen',
        'ancho',
        'alto',
        'transparente',
        'elementos',
        'id_log',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'transparente' => 'boolean',
        'ancho' => 'integer',
        'alto' => 'integer',
        'elementos' => 'array',
    ];
}

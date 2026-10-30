<?php

namespace App\Models\Carnets;

use Illuminate\Database\Eloquent\Model;

class CarnetPlantilla extends Model
{
    protected $table = 'carnet_plantillas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
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

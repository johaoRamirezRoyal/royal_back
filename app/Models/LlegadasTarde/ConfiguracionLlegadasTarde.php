<?php

namespace App\Models\LlegadasTarde;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionLlegadasTarde extends Model
{
    protected $table = 'configuracion_llegadas_tarde';

    public $timestamps = false;

    protected $fillable = [
        'hora_limite',
        'cantidad_limite',
    ];

    protected $casts = [
        'cantidad_limite' => 'integer',
    ];
}

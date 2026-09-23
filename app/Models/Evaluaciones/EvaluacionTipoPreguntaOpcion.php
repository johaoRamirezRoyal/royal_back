<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionTipoPreguntaOpcion extends Model
{
    protected $table = 'evaluaciones_tipos_pregunta_opciones';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_tipo_pregunta',
        'texto',
        'valor',
        'orden',
    ];

    protected $casts = [
        'valor' => 'float',
    ];
}

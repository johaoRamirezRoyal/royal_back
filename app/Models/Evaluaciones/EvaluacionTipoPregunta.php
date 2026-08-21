<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionTipoPregunta extends Model
{
    protected $table = 'evaluaciones_tipos_pregunta';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'slug',
    ];

    public function preguntas()
    {
        return $this->hasMany(EvaluacionPregunta::class, 'id_tipo_pregunta');
    }
}

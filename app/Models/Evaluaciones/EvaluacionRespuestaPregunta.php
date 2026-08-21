<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionRespuestaPregunta extends Model
{
    protected $table = 'evaluaciones_respuestas_pregunta';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_respuesta_evaluacion',
        'id_pregunta',
        'id_opcion',
        'valor_texto',
        'comentario',
    ];

    protected $casts = [
        'id_respuesta_evaluacion' => 'integer',
        'id_pregunta' => 'integer',
        'id_opcion' => 'integer',
    ];

    public function respuestaEvaluacion()
    {
        return $this->belongsTo(EvaluacionRespuestaEvaluacion::class, 'id_respuesta_evaluacion');
    }

    public function pregunta()
    {
        return $this->belongsTo(EvaluacionPregunta::class, 'id_pregunta');
    }

    public function opcion()
    {
        return $this->belongsTo(EvaluacionOpcionPregunta::class, 'id_opcion');
    }
}

<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionPregunta extends Model
{
    protected $table = 'evaluaciones_preguntas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_seccion',
        'id_tipo_pregunta',
        'texto',
        'obligatoria',
        'permite_comentario',
        'orden',
    ];

    protected $casts = [
        'id_seccion' => 'integer',
        'id_tipo_pregunta' => 'integer',
        'obligatoria' => 'integer',
        'permite_comentario' => 'integer',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'obligatoria' => 1,
        'permite_comentario' => 0,
        'orden' => 0,
    ];

    public function seccion()
    {
        return $this->belongsTo(EvaluacionSeccion::class, 'id_seccion');
    }

    public function tipo()
    {
        return $this->belongsTo(EvaluacionTipoPregunta::class, 'id_tipo_pregunta');
    }

    public function opciones()
    {
        return $this->hasMany(EvaluacionOpcionPregunta::class, 'id_pregunta')->orderBy('orden');
    }

    public function respuestas()
    {
        return $this->hasMany(EvaluacionRespuestaPregunta::class, 'id_pregunta');
    }
}

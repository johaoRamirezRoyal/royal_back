<?php

namespace App\Models\Encuestas;

use App\Models\Evaluaciones\EvaluacionTipoPregunta;
use Illuminate\Database\Eloquent\Model;

class EncuestaPregunta extends Model
{
    protected $table = 'encuestas_preguntas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_encuesta',
        'id_tipo_pregunta',
        'texto',
        'obligatoria',
        'orden',
    ];

    protected $casts = [
        'id_encuesta' => 'integer',
        'id_tipo_pregunta' => 'integer',
        'obligatoria' => 'integer',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'obligatoria' => 1,
        'orden' => 0,
    ];

    public function encuesta()
    {
        return $this->belongsTo(Encuesta::class, 'id_encuesta');
    }

    // Reusa el catálogo de tipos de pregunta de Evaluaciones — ver migración
    // create_encuestas_preguntas_table.
    public function tipo()
    {
        return $this->belongsTo(EvaluacionTipoPregunta::class, 'id_tipo_pregunta');
    }

    public function opciones()
    {
        return $this->hasMany(EncuestaOpcionPregunta::class, 'id_pregunta')->orderBy('orden');
    }
}

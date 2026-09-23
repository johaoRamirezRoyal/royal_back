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

    /** Opciones que se precargan al crear una pregunta de este tipo (Evaluaciones y Encuestas). */
    public function opciones()
    {
        return $this->hasMany(EvaluacionTipoPreguntaOpcion::class, 'id_tipo_pregunta')->orderBy('orden')->orderBy('id');
    }
}

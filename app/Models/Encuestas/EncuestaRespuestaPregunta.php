<?php

namespace App\Models\Encuestas;

use Illuminate\Database\Eloquent\Model;

class EncuestaRespuestaPregunta extends Model
{
    protected $table = 'encuestas_respuestas_pregunta';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_respuesta',
        'id_pregunta',
        'id_opcion',
        'valor_texto',
    ];

    protected $casts = [
        'id_respuesta' => 'integer',
        'id_pregunta' => 'integer',
        'id_opcion' => 'integer',
    ];

    public function respuesta()
    {
        return $this->belongsTo(EncuestaRespuesta::class, 'id_respuesta');
    }

    public function pregunta()
    {
        return $this->belongsTo(EncuestaPregunta::class, 'id_pregunta');
    }

    public function opcion()
    {
        return $this->belongsTo(EncuestaOpcionPregunta::class, 'id_opcion');
    }
}

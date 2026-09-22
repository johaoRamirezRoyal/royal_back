<?php

namespace App\Models\Encuestas;

use App\Models\Reservas\Reservas;
use App\Models\Reservas\Salones;
use Illuminate\Database\Eloquent\Model;

class EncuestaRespuesta extends Model
{
    protected $table = 'encuestas_respuestas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_encuesta',
        'id_salon',
        'id_reserva',
    ];

    protected $casts = [
        'id_encuesta' => 'integer',
        'id_salon' => 'integer',
        'id_reserva' => 'integer',
    ];

    public function encuesta()
    {
        return $this->belongsTo(Encuesta::class, 'id_encuesta');
    }

    public function salon()
    {
        return $this->belongsTo(Salones::class, 'id_salon');
    }

    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_reserva');
    }

    public function respuestasPreguntas()
    {
        return $this->hasMany(EncuestaRespuestaPregunta::class, 'id_respuesta');
    }
}

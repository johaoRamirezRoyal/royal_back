<?php

namespace App\Models\Evaluaciones;

use App\Models\Usuarios\Usuario;
use App\Models\Usuarios\Nivel;
use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\Periodo;
use Illuminate\Database\Eloquent\Model;

class EvaluacionRespuestaEvaluacion extends Model
{
    protected $table = 'evaluaciones_respuestas_evaluacion';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_evaluacion',
        'id_user',
        'id_evaluado',
        'id_nivel',
        'id_periodo',
        'id_anio_escolar',
        'anonima',
        'completada_en',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'id_user' => 'integer',
        'id_evaluado' => 'integer',
        'id_nivel' => 'integer',
        'id_periodo' => 'integer',
        'id_anio_escolar' => 'integer',
        'anonima' => 'integer',
        'completada_en' => 'datetime',
    ];

    protected $attributes = [
        'anonima' => 0,
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function evaluado()
    {
        return $this->belongsTo(Usuario::class, 'id_evaluado', 'id_user');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel');
    }

    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo');
    }

    // Año escolar vigente al momento de la respuesta — resuelto aparte de `periodo` (ver
    // migración `add_id_anio_escolar_to_evaluaciones_respuestas_evaluacion_table`), no
    // derivado de `periodo.id_anio`.
    public function anioEscolar()
    {
        return $this->belongsTo(Anio::class, 'id_anio_escolar');
    }

    public function respuestasPreguntas()
    {
        return $this->hasMany(EvaluacionRespuestaPregunta::class, 'id_respuesta_evaluacion');
    }
}

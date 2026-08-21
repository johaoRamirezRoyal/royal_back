<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionOpcionPregunta extends Model
{
    protected $table = 'evaluaciones_opciones_pregunta';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_pregunta',
        'texto',
        'valor',
        'orden',
    ];

    protected $casts = [
        'id_pregunta' => 'integer',
        'valor' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function pregunta()
    {
        return $this->belongsTo(EvaluacionPregunta::class, 'id_pregunta');
    }
}

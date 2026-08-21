<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionNivel extends Model
{
    protected $table = 'evaluaciones_nivel';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_evaluacion',
        'id_nivel',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'id_nivel' => 'integer',
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion');
    }

    public function nivel()
    {
        return $this->belongsTo(\App\Models\Usuarios\Nivel::class, 'id_nivel');
    }
}

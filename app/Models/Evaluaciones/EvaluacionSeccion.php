<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionSeccion extends Model
{
    protected $table = 'evaluaciones_secciones';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_evaluacion',
        'titulo',
        'descripcion',
        'porcentaje',
        'orden',
        'activo',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'porcentaje' => 'decimal:2',
        'orden' => 'integer',
        'activo' => 'integer',
    ];

    protected $attributes = [
        'porcentaje' => '100.00',
        'orden' => 0,
        'activo' => 1,
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion');
    }

    public function preguntas()
    {
        return $this->hasMany(EvaluacionPregunta::class, 'id_seccion')->orderBy('orden');
    }
}

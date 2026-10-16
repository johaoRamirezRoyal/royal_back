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
        // Todas sus preguntas (también las futuras) permiten «No aplica» al responder.
        'permite_no_aplica',
        'orden',
        'activo',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'porcentaje' => 'decimal:2',
        'permite_no_aplica' => 'integer',
        'orden' => 'integer',
        'activo' => 'integer',
    ];

    protected $attributes = [
        'porcentaje' => '100.00',
        'permite_no_aplica' => 0,
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

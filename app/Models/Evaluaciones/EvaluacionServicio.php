<?php

namespace App\Models\Evaluaciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionServicio extends Model
{
    protected $table = 'evaluaciones_servicios';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'integer',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function evaluaciones()
    {
        return $this->hasMany(Evaluacion::class, 'id_servicio');
    }
}

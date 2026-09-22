<?php

namespace App\Models\Encuestas;

use Illuminate\Database\Eloquent\Model;

class Encuesta extends Model
{
    protected $table = 'encuestas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'titulo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'integer',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function preguntas()
    {
        return $this->hasMany(EncuestaPregunta::class, 'id_encuesta')->orderBy('orden');
    }

    public function respuestas()
    {
        return $this->hasMany(EncuestaRespuesta::class, 'id_encuesta');
    }

    public function salones()
    {
        return $this->hasMany(EncuestaSalon::class, 'id_encuesta');
    }
}

<?php

namespace App\Models\Encuestas;

use Illuminate\Database\Eloquent\Model;

class EncuestaOpcionPregunta extends Model
{
    protected $table = 'encuestas_opciones_pregunta';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_pregunta',
        'texto',
        'orden',
    ];

    protected $casts = [
        'id_pregunta' => 'integer',
        'orden' => 'integer',
    ];

    public function pregunta()
    {
        return $this->belongsTo(EncuestaPregunta::class, 'id_pregunta');
    }
}

<?php

namespace App\Models\Encuestas;

use App\Models\Reservas\Salones;
use Illuminate\Database\Eloquent\Model;

class EncuestaSalon extends Model
{
    protected $table = 'encuestas_salon';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_salon',
        'id_encuesta',
        'token_publico',
    ];

    protected $casts = [
        'id_salon' => 'integer',
        'id_encuesta' => 'integer',
    ];

    public function salon()
    {
        return $this->belongsTo(Salones::class, 'id_salon');
    }

    public function encuesta()
    {
        return $this->belongsTo(Encuesta::class, 'id_encuesta');
    }
}

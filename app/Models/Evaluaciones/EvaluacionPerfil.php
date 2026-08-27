<?php

namespace App\Models\Evaluaciones;

use App\Models\Usuarios\Perfil;
use Illuminate\Database\Eloquent\Model;

class EvaluacionPerfil extends Model
{
    protected $table = 'evaluaciones_perfil';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_evaluacion',
        'id_perfil',
    ];

    protected $casts = [
        'id_evaluacion' => 'integer',
        'id_perfil' => 'integer',
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'id_perfil', 'id_perfil');
    }
}

<?php

namespace App\Models\GestionAcademica;

use App\Models\AnioEscolar\Anio;
use Illuminate\Database\Eloquent\Model;

class FranjaHoraria extends Model
{
    protected $table = 'academico_franja_horaria';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_anio_escolar',
        'id_dia_semana',
        'hora_inicio',
        'hora_fin',
        'orden',
        'tipo',
    ];

    public function anioEscolar()
    {
        return $this->belongsTo(Anio::class, 'id_anio_escolar', 'id');
    }

    public function diaSemana()
    {
        return $this->belongsTo(DiaSemana::class, 'id_dia_semana', 'id');
    }
}

<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

class HorarioClase extends Model
{
    protected $table = 'academico_horario_clase';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_carga_academica',
        'id_franja_horaria',
        'tipo',
        'descripcion',
    ];

    public function cargaAcademica()
    {
        return $this->belongsTo(CargaAcademica::class, 'id_carga_academica', 'id');
    }

    public function franjaHoraria()
    {
        return $this->belongsTo(FranjaHoraria::class, 'id_franja_horaria', 'id');
    }
}

<?php

namespace App\Models\Admisiones;

use Illuminate\Database\Eloquent\Model;

class ReferenciasFamiliares extends Model
{
    protected $table = "admisiones_referencias_familiares";

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'nombre',
        'parentesco',
        'direccion_residencia',
        'telefono_residencia',
        'recomendacion_colegio',
        'motivo_ingreso',
    ];

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion');
    }
}

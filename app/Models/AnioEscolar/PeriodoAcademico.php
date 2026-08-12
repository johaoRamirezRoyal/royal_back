<?php
namespace App\Models\AnioEscolar;

use Illuminate\Database\Eloquent\Model;

class PeriodoAcademico extends Model
{
    protected $table = 'periodo_academico';

    protected $fillable = [
        'id_anio_escolar',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'activo'
    ];

    public function anioEscolar(){
        return $this->belongsTo(Anio::class, 'id_anio_escolar');
    }
}
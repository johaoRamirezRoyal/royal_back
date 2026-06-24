<?php 

namespace App\Models\LlegadasTarde;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class LlegadasTarde extends Model
{
    protected $table = 'llegadas_tardes';

    protected $fillable = [
        'id_alumno',
        'id_periodo_academico',
        'fecha',
        'hora'
    ];

    public function alumno(){
        return $this->belongsTo(Usuario::class, 'id_alumno', 'id_user');
    }

    public function periodoAcademico(){
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo_academico');
    }

}
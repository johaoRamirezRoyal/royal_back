<?php

namespace App\Models\Admisiones;

use App\Enums\ViveCon;
use App\Models\AnioEscolar\Anio;
use Illuminate\Database\Eloquent\Model;

class Aspirante extends Model
{
    protected $table = "admisiones_aspirantes";

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'nombre_completo',
        'lugar_nacimiento',
        'fecha_nacimiento',
        'edad',
        'sexo',
        'lengua_materna',
        'otros_idiomas',
        'religion',

        'vive_con',
        'num_hermanos',
        'posicion_entre_hermanos',
        'tiene_hermanos_colegio',
        'info_hermanos_colegio',

        'antecedentes_escolares',
        'grado_aplica',
        'anio_academico',

        'fecha_registro'

    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_registro' => 'datetime',
        'tiene_hermanos_colegio' => 'boolean',
        'num_hermanos' => 'integer',
        'posicion_entre_hermanos' => 'integer',
        'edad' => 'integer',
        'vive_con' => ViveCon::class,
    ];

    public function anioAcademico()
    {
        return $this->belongsTo(Anio::class, 'anio_academico', 'id');
    }
    
    public function familiares(){
        return $this->hasMany(Familiares::class, 'aspirante_id', 'id');
    }

    public function inscripcion(){
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id', 'id');
    }
}

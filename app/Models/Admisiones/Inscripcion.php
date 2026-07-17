<?php

namespace App\Models\Admisiones;

use App\Models\Admisiones\Aspirante;
use App\Models\AnioEscolar\Anio;
use App\Models\Estado;
use App\Models\HistoriaClinica\HceCognitivoLenguaje;
use App\Models\HistoriaClinica\HceDesarrolloPsicomotor;
use App\Models\HistoriaClinica\HceEmbarazoParto;
use App\Models\HistoriaClinica\HceEstructuraFamiliar;
use App\Models\HistoriaClinica\HceHermano;
use App\Models\HistoriaClinica\HceHistoriaEscolar;
use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Models\HistoriaClinica\HcePsicoafectiva;
use App\Models\HistoriaClinica\HceRemision;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    protected $table = 'admisiones_inscripciones';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'estado',
        'anio_academico',
        'id_usuario_registro',
        'fecha_inscripcion',
        'updated_by'
    ];

    public function aspirante()
    {
        return $this->hasOne(Aspirante::class, 'id_inscripcion', 'id');
    }

    public function estadoInscripcion()
    {
        return $this->belongsTo(Estado::class, 'estado', 'id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_user');
    }

    public function updatedBy()
    {
        return $this->belongsTo(Usuario::class, 'updated_by', 'id_user');
    }

    public function anioAcademico()
    {
        return $this->belongsTo(Anio::class, 'anio_academico', 'id');
    }

    public function documento()
    {
        return $this->hasMany(Documento::class, 'id_inscripcion', 'id');
    }

    public function referenciaFamiliares()
    {
        return $this->hasMany(ReferenciasFamiliares::class, 'id_inscripcion');
    }

    public function informacionMedica()
    {
        return $this->hasMany(InformacionMedica::class, 'id_inscripcion', 'id');
    }

    public function familiares(){
        return $this->hasMany(Familiares::class, 'id_inscripcion', 'id');
    }

    /**
     * HISTORIA CLINICA
     */

    public function cognitivoLenguaje()
    {
        return $this->hasOne(HceCognitivoLenguaje::class, 'id_inscripcion');
    }

    public function desarrolloPsicomotor()
    {
        return $this->hasOne(HceDesarrolloPsicomotor::class, 'id_inscripcion');
    }

    public function embarazoParto()
    {
        return $this->hasOne(HceEmbarazoParto::class, 'id_inscripcion');
    }

    public function estructuraFamiliar()
    {
        return $this->hasOne(HceEstructuraFamiliar::class, 'id_inscripcion');
    }

    public function hermanos()
    {
        return $this->hasMany(HceHermano::class, 'id_inscripcion');
    }

    public function historiaEscolar()
    {
        return $this->hasOne(HceHistoriaEscolar::class, 'id_inscripcion');
    }

    public function observacionesFirmas()
    {
        return $this->hasOne(HceObservacionesFirmas::class, 'id_inscripcion');
    }

    public function psicoafectiva()
    {
        return $this->hasOne(HcePsicoafectiva::class, 'id_inscripcion');
    }

    public function remision()
    {
        return $this->hasOne(HceRemision::class, 'id_inscripcion');
    }
}

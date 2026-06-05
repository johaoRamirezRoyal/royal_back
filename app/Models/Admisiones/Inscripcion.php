<?php

namespace App\Models\Admisiones;

use App\Models\Admisiones\Aspirante;
use App\Models\AnioEscolar\Anio;
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
}

<?php

namespace App\Models\Admisiones;

use App\Enums\ViveCon;
use Illuminate\Database\Eloquent\Model;

class Familiares extends Model
{
    protected $table = "admisiones_familiares";

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'aspirante_id',
        'id_inscripcion',
        'tipo_parentesco',
        'nombre_completo',
        'documento_identidad',
        'lugar_expedicion_doc',
        'estado_civil',
        'idiomas',
        'direccion_residencia',
        'telefono_fijo',
        'celular',
        'email',
        'profesion',
        'empresa_labora',
        'cargo_ocupacion',
        'telefono_oficina',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'tipo_parentesco' => ViveCon::class,
    ];

    public function aspirante(){
        return $this->belongsTo(Aspirante::class, 'aspirante_id', 'id');
    }

    public function inscripcion(){
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id');
    }
}
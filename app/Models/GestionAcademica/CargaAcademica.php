<?php

namespace App\Models\GestionAcademica;

use App\Models\Areas\Cursos;
use Illuminate\Database\Eloquent\Model;

class CargaAcademica extends Model
{
    protected $table = 'academico_carga_academica';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_docente_asignatura',
        'id_curso',
        'activo',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function docenteAsignatura()
    {
        return $this->belongsTo(DocenteAsignatura::class, 'id_docente_asignatura', 'id');
    }

    public function curso()
    {
        return $this->belongsTo(Cursos::class, 'id_curso', 'id');
    }
}

<?php

namespace App\Models\GestionAcademica;

use App\Models\Areas\Cursos;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class CargaAcademica extends Model
{
    protected $table = 'academico_carga_academica';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_docente_asignatura',
        'id_docente',
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

    /** Docente directo de una carga "suelta" (sin asignatura) — ver
     * id_docente_asignatura de esta misma tabla y la migración
     * add_id_docente_to_academico_carga_academica_table. */
    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'id_docente', 'id_user');
    }

    public function curso()
    {
        return $this->belongsTo(Cursos::class, 'id_curso', 'id');
    }

    /** Id del docente de esta carga, sea cual sea el camino: directo (carga "suelta") o
     * vía docenteAsignatura (carga con asignatura, el caso normal). Requiere
     * `docenteAsignatura` cargada de antemano para no disparar una consulta N+1 en un
     * loop — todos los callers de este accessor ya la eager-cargan. */
    public function getIdDocenteEfectivoAttribute(): ?int
    {
        return $this->id_docente ?? $this->docenteAsignatura?->id_docente;
    }
}

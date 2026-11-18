<?php

namespace App\Models\FotosCurso;

use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\Asignatura;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una publicación de fotos (clase o actividad) de una asignatura, para uno o varios cursos. Ver
 * FotosCursoService.
 */
class PublicacionFotosCurso extends Model
{
    protected $table = 'fotos_curso_publicaciones';

    protected $fillable = [
        'id_anio_escolar',
        'id_docente',
        'id_asignatura',
        'fecha',
        'descripcion',
        'confirma_autorizacion',
    ];

    protected $casts = [
        'id_anio_escolar' => 'integer',
        'id_docente' => 'integer',
        'id_asignatura' => 'integer',
        'fecha' => 'date:Y-m-d',
        'confirma_autorizacion' => 'boolean',
    ];

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoCurso::class, 'id_publicacion')->orderBy('orden');
    }

    /** Los cursos donde se ve (tabla puente, sin FK a `curso`). */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Cursos::class, 'fotos_curso_publicacion_curso', 'id_publicacion', 'id_curso');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_docente', 'id_user');
    }
}

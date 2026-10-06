<?php

namespace App\Models\Capacitaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tabla legacy `capacitacion_curso` — una capacitación. `imagen` = URL de Cloudinary (nuevas) o ruta en el disco de uploads (antiguas). */
class CapCurso extends Model
{
    protected $table = 'capacitacion_curso';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'imagen', 'id_log', 'activo'];

    protected $casts = [
        'id_log' => 'integer',
        'activo' => 'integer',
    ];

    public function modulos(): HasMany
    {
        return $this->hasMany(CapModulo::class, 'id_curso')->orderBy('id');
    }

    // `capacitacion_preguntas.id_capacitacion` es el id del curso (prueba final única por curso).
    public function preguntas(): HasMany
    {
        return $this->hasMany(CapPregunta::class, 'id_capacitacion')->orderBy('id');
    }
}

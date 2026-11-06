<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\NivelAcademico;
use Illuminate\Database\Eloquent\Model;

/** Desempeño de la escala cualitativa (Superior, Alto...). Sin niveles = escala general. */
class EscalaValoracion extends Model
{
    protected $table = 'academico_escala_valoracion';

    protected $fillable = ['nombre', 'abreviatura', 'descripcion', 'color', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];

    public function niveles()
    {
        return $this->belongsToMany(NivelAcademico::class, 'academico_escala_valoracion_nivel', 'id_escala', 'id_nivel_academico');
    }
}

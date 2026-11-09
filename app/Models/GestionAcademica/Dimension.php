<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Unidad evaluable de la parte cualitativa (la tabla conserva el nombre "dimension"). `tipo`:
 * DIMENSION (cognitiva, comunicativa... en preescolar), AREA (`id_area` → academico_area) o
 * ASIGNATURA (`id_asignatura` → academico_asignatura), sin FK. Cada grado elige las suyas
 * (academico_grado_dimension) y cada logro pertenece a una de las de su grado. No se borran: se
 * desactivan.
 */
class Dimension extends Model
{
    public const DIMENSION = 'DIMENSION';
    public const AREA = 'AREA';
    public const ASIGNATURA = 'ASIGNATURA';

    protected $table = 'academico_dimension';

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'id_area', 'id_asignatura', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer', 'id_area' => 'integer', 'id_asignatura' => 'integer'];

    protected $attributes = ['tipo' => self::DIMENSION];

    /**
     * Lo mismo que el CHECK ck_unidad_tipo (MySQL < 8.0.16 lo ignora): DIMENSION sin área ni
     * asignatura; AREA solo con área; ASIGNATURA solo con asignatura. Mensaje de error o null.
     */
    public static function errorDeTipo(?string $tipo, ?int $idArea, ?int $idAsignatura): ?string
    {
        return match ($tipo) {
            self::DIMENSION => $idArea === null && $idAsignatura === null ? null : 'Una dimensión no lleva área ni asignatura.',
            self::AREA => $idArea !== null && $idAsignatura === null ? null : 'Una unidad de tipo área necesita el área (y no una asignatura).',
            self::ASIGNATURA => $idAsignatura !== null && $idArea === null ? null : 'Una unidad de tipo asignatura necesita la asignatura (y no un área).',
            default => 'Tipo de unidad no válido: DIMENSION, AREA o ASIGNATURA.',
        };
    }
}

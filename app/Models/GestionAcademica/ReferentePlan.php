<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado de los referentes de un grado en un periodo. Solo se califica APROBADO.
 * Ver NotasService (transiciones) y docs/contexto/modulos/notas.md.
 */
class ReferentePlan extends Model
{
    public const BORRADOR = 'BORRADOR';
    public const EN_REVISION = 'EN_REVISION';
    public const APROBADO = 'APROBADO';
    public const DEVUELTO = 'DEVUELTO';

    protected $table = 'academico_referente_plan';

    protected $fillable = ['id_grado', 'id_periodo_academico', 'estado'];

    protected $attributes = ['estado' => self::BORRADOR];

    public function historial()
    {
        return $this->hasMany(ReferentePlanHistorial::class, 'id_plan');
    }
}

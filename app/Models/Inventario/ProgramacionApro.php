<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Avances/aprobaciones de una programación (tabla legacy `programacion_apro`): estado al que
 * pasó, observación y archivo soporte (cotización, factura, evidencia).
 */
class ProgramacionApro extends Model
{
    protected $table = 'programacion_apro';

    public $timestamps = false;

    protected $fillable = [
        'id_programacion',
        'id_compra_asociada',
        'fecha_registro',
        'observacion',
        'usuario',
        'estado',
        'archivo',
    ];
}

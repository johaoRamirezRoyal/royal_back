<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Programación de mantenimientos (tabla legacy `programacion_mant`).
 * estado: 0 Pendiente, 1 Terminado, 2 Completado parcial, 3 Aplazado, 4 Cancelado.
 * tipo: 1 Sistemas, 2 Operativos. periodo: `periodos.id`. usuario: id_user como texto (legacy).
 */
class ProgramacionMant extends Model
{
    protected $table = 'programacion_mant';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'descripcion',
        'tipo',
        'anio',
        'mes',
        'fecha_registro',
        'fecha_fin',
        'usuario',
        'periodo',
        'is_inventario',
        'estado',
    ];

    public function avances()
    {
        return $this->hasMany(ProgramacionApro::class, 'id_programacion');
    }
}

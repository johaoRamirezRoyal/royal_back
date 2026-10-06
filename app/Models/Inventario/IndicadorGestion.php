<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/** Indicador de gestión de mantenimiento guardado por periodo (tabla legacy `indicadores_gestion`). */
class IndicadorGestion extends Model
{
    protected $table = 'indicadores_gestion';

    protected $primaryKey = 'id';

    protected $fillable = [
        'categoria',
        'anio',
        'periodo',
        'departamento',
        'cantidad_mantenimientos',
        'cantidad_equipos',
        'porcentaje_cumplimiento',
        'gestion',
        'analisis_gestion',
        'id_log',
    ];

    public $timestamps = false;
}

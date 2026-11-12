<?php

namespace App\Models\CursosAltura;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trabajo en altura: de un reporte/mantenimiento de Áreas Comunes (1 a 1 con `reportes`) o,
 * con `id_reporte` null, creado desde Gestión Humana con sus propios datos del permiso (tipo,
 * bloque, área, elemento, descripción). Tiene su fecha propia, los operativos con curso de
 * alturas que lo hacen y, opcionales, altura, hora de inicio y fecha de finalización.
 */
class TrabajoAltura extends Model
{
    protected $table = 'trabajos_altura';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha_trabajo',
        'tipo_trabajo',
        'id_bloque',
        'id_area',
        'elemento',
        'descripcion',
        'altura_aproximada',
        'hora_inicio',
        'fecha_finalizacion',
        'finalizado_en',
        'finalizado_por',
        'user_log',
    ];

    protected function casts(): array
    {
        return [
            'id_reporte' => 'integer',
            'fecha_trabajo' => 'date:Y-m-d',
            'id_bloque' => 'integer',
            'id_area' => 'integer',
            'altura_aproximada' => 'decimal:2',
            'fecha_finalizacion' => 'date:Y-m-d',
            'finalizado_en' => 'datetime',
        ];
    }

    /** Creado desde Gestión Humana, sin reporte ni mantenimiento. */
    public function esManual(): bool
    {
        return $this->id_reporte === null;
    }

    public function operativos(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'trabajos_altura_operativos', 'id_trabajo', 'id_operativo', 'id', 'id_user');
    }
}

<?php

namespace App\Models\CursosAltura;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trabajo en altura de un reporte/mantenimiento de Áreas Comunes (1 a 1 con `reportes`):
 * su fecha propia y los operativos con curso de alturas que lo hacen.
 */
class TrabajoAltura extends Model
{
    protected $table = 'trabajos_altura';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha_trabajo',
        'user_log',
    ];

    protected function casts(): array
    {
        return [
            'id_reporte' => 'integer',
            'fecha_trabajo' => 'date:Y-m-d',
        ];
    }

    public function operativos(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'trabajos_altura_operativos', 'id_trabajo', 'id_operativo', 'id', 'id_user');
    }
}

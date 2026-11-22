<?php

namespace App\Models\Cafeteria;

use App\Enums\Cafeteria\EstadoAsignacion;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionPersonal extends Model
{
    protected $table = 'caf_asignaciones_personal';

    protected $fillable = ['id_cafeteria', 'id_user', 'id_rol_operativo', 'asignado_por', 'fecha_inicio', 'fecha_fin', 'estado'];

    protected $casts = [
        'id_cafeteria' => 'integer',
        'id_user' => 'integer',
        'id_rol_operativo' => 'integer',
        'asignado_por' => 'integer',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'estado' => EstadoAsignacion::class,
    ];

    /** `vigente` viaja en el JSON: el frontend distingue una ACTIVA vencida o futura de una que rige hoy. */
    protected $appends = ['vigente'];

    /**
     * Vigentes en una fecha (hoy por defecto): ACTIVA y con la fecha dentro de su vigencia. Una
     * ACTIVA cuya `fecha_fin` ya pasó cuenta como vencida sin que nada la cambie de estado: no
     * hay un comando que las cierre (los programados solo corren en la conexión por defecto).
     */
    public function scopeVigentes(Builder $query, ?string $fecha = null): Builder
    {
        $dia = $fecha ?? now()->toDateString();

        return $query->where('estado', EstadoAsignacion::ACTIVA)
            ->where('fecha_inicio', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $dia));
    }

    protected function vigente(): Attribute
    {
        return Attribute::get(function (): bool {
            $hoy = now()->toDateString();

            return $this->estado === EstadoAsignacion::ACTIVA
                && $this->fecha_inicio?->toDateString() <= $hoy
                && ($this->fecha_fin === null || $this->fecha_fin->toDateString() >= $hoy);
        });
    }

    public function cafeteria(): BelongsTo
    {
        return $this->belongsTo(Cafeteria::class, 'id_cafeteria');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(RolOperativo::class, 'id_rol_operativo');
    }
}

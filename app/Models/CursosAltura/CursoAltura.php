<?php

namespace App\Models\CursosAltura;

use App\Models\Usuarios\Usuario;
use App\Services\permisosLicencias\PermisosLicenciasServices;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoAltura extends Model
{
    protected $table = 'cursos_altura';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'ruta_certificado',
        'fecha_expedicion',
        'fecha_finalizacion',
        'user_log',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'id_user' => 'integer',
            'activo' => 'integer',
            'fecha_expedicion' => 'date:Y-m-d',
            'fecha_finalizacion' => 'date:Y-m-d',
            'fechareg' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    /** Activo y dentro de su vigencia hoy (sin fecha de finalización = no vence). */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = Carbon::today()->toDateString();

        return $query->where('activo', 1)
            ->whereDate('fecha_expedicion', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('fecha_finalizacion')->orWhereDate('fecha_finalizacion', '>=', $hoy));
    }

    /** Si el usuario es un operativo activo con curso de alturas vigente. */
    public static function operativoVigente(int $idUser): bool
    {
        return self::vigentes()
            ->where('id_user', $idUser)
            ->whereHas('usuario', fn ($q) => self::filtrarOperativos($q))
            ->exists();
    }

    /** Usuarios activos con perfil operativo (los mismos asignables de Permisos y Licencias). */
    public static function filtrarOperativos(Builder $query): Builder
    {
        return $query->where('estado', 'activo')->whereIn('perfil', PermisosLicenciasServices::PERFILES_ASIGNABLES);
    }

    /**
     * Regla de validación para `id_operativo` de un reporte o mantenimiento.
     *
     * @return array<int, mixed>
     */
    public static function reglaOperativo(): array
    {
        return ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
            if (! self::operativoVigente((int) $value)) {
                $fail('El operativo seleccionado no tiene un curso de altura vigente.');
            }
        }];
    }
}

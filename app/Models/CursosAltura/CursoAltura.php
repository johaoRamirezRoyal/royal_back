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

    /** Activo y dentro de su vigencia en $fecha (hoy por defecto; sin finalización = no vence). */
    public function scopeVigentes(Builder $query, ?string $fecha = null): Builder
    {
        $dia = $fecha ? Carbon::parse($fecha)->toDateString() : Carbon::today()->toDateString();

        return $query->where('activo', 1)
            ->whereDate('fecha_expedicion', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('fecha_finalizacion')->orWhereDate('fecha_finalizacion', '>=', $dia));
    }

    /** Si el usuario es un operativo activo con curso de alturas vigente en $fecha (hoy por defecto). */
    public static function operativoVigente(int $idUser, ?string $fecha = null): bool
    {
        return self::vigentes($fecha)
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
     * Reglas de `id_operativos` (operativos de un trabajo en altura): cada uno con curso
     * vigente en $fecha (hoy por defecto, al reportar/programar).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglasOperativos(?string $fecha = null): array
    {
        return [
            'id_operativos' => ['nullable', 'array'],
            'id_operativos.*' => ['integer', 'distinct', function (string $attribute, mixed $value, \Closure $fail) use ($fecha) {
                if (! self::operativoVigente((int) $value, $fecha)) {
                    $nombre = Usuario::where('id_user', (int) $value)->selectRaw("CONCAT(nombre, ' ', apellido) AS n")->value('n') ?? 'El operativo seleccionado';
                    $fail($fecha ? "{$nombre} no tiene un curso de altura vigente en la fecha del trabajo." : "{$nombre} no tiene un curso de altura vigente.");
                }
            }],
        ];
    }
}

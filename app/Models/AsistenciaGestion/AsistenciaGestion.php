<?php

namespace App\Models\AsistenciaGestion;

use App\Models\Usuarios\Usuario;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AsistenciaGestion extends Model
{
    // Fallback cuando ninguna institución configuró horarios/bandas todavía (ver
    // getPuntualidadAttribute) — mismos cortes que existían antes de que la puntualidad
    // fuera configurable.
    public const HORA_LIMITE_PUNTUALIDAD = '07:15:00';
    public const HORA_INICIO_JUSTO_A_TIEMPO = '07:00:00';

    // Cache en memoria por request: los horarios/bandas activos se piden una sola vez
    // sin importar cuántas filas de AsistenciaGestion se serialicen en la misma respuesta.
    private static ?Collection $horariosCache = null;

    protected $table = 'asistencia_gestion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'fecha_asistencia',
        'hora_asistencia',
        'hora_salida',
        'observacion',
        'revocado',
        'fechareg',
        'QR',
    ];

    protected $casts = [
        'fecha_asistencia' => 'date',
        'hora_asistencia' => 'date:H:i:s',
        'hora_salida' => 'date:H:i:s',
        'revocado' => 'boolean',
        'fechareg' => 'datetime',
    ];

    protected $appends = ['puntualidad', 'estado'];

    public function getPuntualidadAttribute(): ?string
    {
        if (!$this->hora_asistencia) {
            return null;
        }

        $hora = $this->hora_asistencia->format('H:i:s');
        $grupoId = $this->usuario ? (hikvisionattendanceService::GROUP_ID_POR_PERFIL[(int) $this->usuario->perfil] ?? null) : null;
        $horario = self::horarioAplicable($grupoId);

        if (!$horario) {
            return match (true) {
                $hora > self::HORA_LIMITE_PUNTUALIDAD => 'atrasado',
                $hora >= self::HORA_INICIO_JUSTO_A_TIEMPO => 'Justo a tiempo',
                default => 'a tiempo',
            };
        }

        foreach ($horario->bandas as $banda) {
            $desdeOk = $banda->hora_desde === null || $hora >= $banda->hora_desde;
            $hastaOk = $banda->hora_hasta === null || $hora <= $banda->hora_hasta;

            if ($desdeOk && $hastaOk) {
                return $banda->nombre;
            }
        }

        return null;
    }

    /**
     * Horario activo del grupo dado, o el horario global (grupo_id null) si no hay uno
     * específico, que además incluya el día de la semana actual en `dias_habiles`. Un
     * horario que no cubre hoy (ej. Lun-Vie un sábado) no aplica, aunque esté activo y
     * coincida el grupo — cae al fallback de horarioAplicable() == null (ver
     * getPuntualidadAttribute). Público: también lo usa
     * AsistenciaGestionService::cerrarAsistenciasVencidas() para saber la hora de salida
     * esperada de cada usuario con marcación abierta.
     */
    public static function horarioAplicable(?int $grupoId): ?AsistenciaHorario
    {
        if (self::$horariosCache === null) {
            self::$horariosCache = AsistenciaHorario::with('bandas')->where('activo', true)->get();
        }

        $diaHoy = (int) now()->isoWeekday();

        $horariosDeHoy = self::$horariosCache->filter(
            fn (AsistenciaHorario $horario) => in_array($diaHoy, $horario->dias_habiles ?? [], true)
        );

        return $horariosDeHoy->firstWhere('grupo_id', $grupoId)
            ?? $horariosDeHoy->firstWhere('grupo_id', null);
    }

    // Toda fila en esta tabla es, por definición, una llegada registrada.
    // Las inasistencias no viven aquí: ver AsistenciaGestionService::obtenerFaltantesDelDia().
    public function getEstadoAttribute(): string
    {
        return 'llegada';
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function scopeFechaDesde(Builder $query, string $fecha): Builder
    {
        return $query->where('fecha_asistencia', '>=', $fecha);
    }

    public function scopeFechaHasta(Builder $query, string $fecha): Builder
    {
        return $query->where('fecha_asistencia', '<=', $fecha);
    }

    public function scopePorUsuario(Builder $query, int $idUsuario): Builder
    {
        return $query->where('id_user', $idUsuario);
    }

    public function scopePorPerfil(Builder $query, int $idPerfil): Builder
    {
        return $query->whereHas('usuario', fn (Builder $q) => $q->where('perfil', $idPerfil));
    }

    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->where('fecha_asistencia', $fecha ?? now()->toDateString());
    }
}

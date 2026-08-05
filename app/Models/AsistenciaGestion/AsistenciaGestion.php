<?php

namespace App\Models\AsistenciaGestion;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AsistenciaGestion extends Model
{
    // Después de esta hora una llegada registrada cuenta como "atrasado"
    public const HORA_LIMITE_PUNTUALIDAD = '07:15:00';

    // Desde esta hora (inclusive) hasta HORA_LIMITE_PUNTUALIDAD (inclusive) cuenta como "Justo a tiempo"
    public const HORA_INICIO_JUSTO_A_TIEMPO = '07:00:00';

    protected $table = 'asistencia_gestion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'fecha_asistencia',
        'hora_asistencia',
        'hora_salida',
        'fechareg',
    ];

    protected $casts = [
        'fecha_asistencia' => 'date',
        'hora_asistencia' => 'date:H:i:s',
        'hora_salida' => 'date:H:i:s',
        'fechareg' => 'datetime',
    ];

    protected $appends = ['puntualidad', 'estado'];

    public function getPuntualidadAttribute(): ?string
    {
        if (!$this->hora_asistencia) {
            return null;
        }

        $hora = $this->hora_asistencia->format('H:i:s');

        if ($hora > self::HORA_LIMITE_PUNTUALIDAD) {
            return 'atrasado';
        }

        if ($hora >= self::HORA_INICIO_JUSTO_A_TIEMPO) {
            return 'Justo a tiempo';
        }

        return 'a tiempo';
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

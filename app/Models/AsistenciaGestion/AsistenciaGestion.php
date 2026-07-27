<?php

namespace App\Models\AsistenciaGestion;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AsistenciaGestion extends Model
{
    protected $table = 'asistencia_gestion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'fecha_asistencia',
        'hora_asistencia',
        'fechareg',
    ];

    protected $casts = [
        'fecha_asistencia' => 'date',
        'hora_asistencia' => 'date:H:i:s',
        'fechareg' => 'datetime',
    ];

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

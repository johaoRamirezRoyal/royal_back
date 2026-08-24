<?php

namespace App\Models\GestionAcademica;

use App\Models\AnioEscolar\Anio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FranjaHoraria extends Model
{
    protected $table = 'academico_franja_horaria';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_anio_escolar',
        'id_esquema',
        'id_dia_semana',
        'hora_inicio',
        'hora_fin',
        'orden',
        'asignable',
        'color',
        'etiqueta',
        'id_franja_pivote',
    ];

    protected $casts = [
        'asignable' => 'boolean',
    ];

    public function anioEscolar()
    {
        return $this->belongsTo(Anio::class, 'id_anio_escolar', 'id');
    }

    public function esquema()
    {
        return $this->belongsTo(EsquemaHorario::class, 'id_esquema', 'id');
    }

    public function diaSemana()
    {
        return $this->belongsTo(DiaSemana::class, 'id_dia_semana', 'id');
    }

    public function horarioClase(): HasOne
    {
        return $this->hasOne(HorarioClase::class, 'id_franja_horaria', 'id');
    }

    public function pivote()
    {
        return $this->belongsTo(FranjaHoraria::class, 'id_franja_pivote', 'id');
    }

    public function dependientes(): HasMany
    {
        return $this->hasMany(FranjaHoraria::class, 'id_franja_pivote', 'id');
    }
}

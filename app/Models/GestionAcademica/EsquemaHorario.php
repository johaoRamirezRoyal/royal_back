<?php

namespace App\Models\GestionAcademica;

use App\Models\AnioEscolar\Anio;
use App\Models\Usuarios\NivelAcademico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsquemaHorario extends Model
{
    protected $table = 'academico_esquema_horario';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_nivel',
        'id_anio_escolar',
        'activo',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    // id_nivel apunta a nivel_academico desde
    // 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico.
    public function nivel()
    {
        return $this->belongsTo(NivelAcademico::class, 'id_nivel', 'id');
    }

    public function anioEscolar()
    {
        return $this->belongsTo(Anio::class, 'id_anio_escolar', 'id');
    }

    public function franjas(): HasMany
    {
        return $this->hasMany(FranjaHoraria::class, 'id_esquema', 'id');
    }
}

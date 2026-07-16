<?php

namespace App\Models\HistoriaClinica;

use App\Models\Admisiones\Inscripcion as AdmisionesInscripcion;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuarios\Usuario;

class HceDesarrolloPsicomotor extends Model
{
    protected $table = 'hce_desarrollo_psicomotor';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'sostenimiento_cabeza',
        'sentarse_solo',
        'equilibrio',
        'gateo',
        'caminar',
        'seguimiento_ocular',
        'agarre_pinza',
        'abotonarse',
        'recorte',
        'trazo',
        'lateralidad',
        'notas',
        'updated_by',
    ];

    protected $casts = [
        'fechareg' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Inscripción asociada.
     */
    public function inscripcion()
    {
        return $this->belongsTo(AdmisionesInscripcion::class, 'id_inscripcion');
    }

    /**
     * Usuario que realizó la última actualización.
     */
    public function actualizadoPor()
    {
        return $this->belongsTo(Usuario::class, 'updated_by');
    }
}

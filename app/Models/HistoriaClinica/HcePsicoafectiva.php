<?php

namespace App\Models\HistoriaClinica;

use App\Models\Admisiones\Inscripcion as AdmisionesInscripcion;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuarios\Usuario;

class HcePsicoafectiva extends Model
{
    protected $table = 'hce_psicoafectiva';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'actividad_ludica_preferida',
        'expresion_afecto_familiar',
        'normas_disciplinarias_hogar',
        'reaccion_consecuencias',
        'llora_facilmente',
        'pataletas',
        'agresividad',
        'tics',
        'fobias',
        'mentiras',
        'insomnio',
        'duerme_con',
        'alimentacion',
        'dificultades_estomacales',
        'alergias',
        'control_esfinteres',
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

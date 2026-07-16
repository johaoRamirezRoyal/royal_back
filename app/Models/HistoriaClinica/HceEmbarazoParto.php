<?php

namespace App\Models\HistoriaClinica;

use App\Models\Admisiones\Inscripcion as AdmisionesInscripcion;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuarios\Usuario;

class HceEmbarazoParto extends Model
{
    protected $table = 'hce_embarazo_parto';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'embarazo_tipo',
        'tiempo_gestacion',
        'parto_tipo',
        'uso_forceps',
        'dificultades_embarazo',
        'requirio_oxygeno_nacer',
        'lloro_inmediatamente_nacer',
        'presento_ictericia',
        'sufrio_anoxia',
        'convulsiono',
        'presento_erupciones_piel',
        'permanecio_hospital_mas_tiempo',
        'estado_salud_madre',
        'estado_emocional_madre',
        'estado_nino_al_nacer',
        'alimentacion_nino_seno',
        'juega_solo_o_con_otros',
        'sostenia_tetero_solo',
        'usa_cuchara_tenedor',
        'descripcion_alimentacion_hijo',
        'tiene_rabietas',
        'llora_facilmente',
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

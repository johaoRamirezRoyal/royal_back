<?php

namespace App\Models\HistoriaClinica;

use App\Models\Admisiones\Inscripcion as AdmisionesInscripcion;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuarios\Firma;
use App\Models\Usuarios\Usuario;

class HceObservacionesFirmas extends Model
{
    protected $table = 'hce_observaciones_firmas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'observaciones',
        'firma_padre',
        'firma_padre_url',
        'firma_madre',
        'firma_madre_url',
        'id_firma_psicologa',
        'updated_by',
    ];

    protected $appends = [
        'firma_psicologa_url',
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
     * Firma (reutilizable) de la psicóloga.
     */
    public function firmaPsicologa()
    {
        return $this->belongsTo(Firma::class, 'id_firma_psicologa');
    }

    /**
     * Usuario que realizó la última actualización.
     */
    public function actualizadoPor()
    {
        return $this->belongsTo(Usuario::class, 'updated_by');
    }

    public function getFirmaPsicologaUrlAttribute(): ?string
    {
        return $this->firmaPsicologa?->url;
    }
}

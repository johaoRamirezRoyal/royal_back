<?php

namespace App\Models\HistoriaClinica;

use App\Models\Admisiones\Inscripcion as AdmisionesInscripcion;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuarios\Usuario;

class HceCognitivoLenguaje extends Model
{
    protected $table = 'hce_cognitivo_lenguaje';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'comprension_instrucciones',
        'desarrollo_lectoescritura',
        'dominio_segunda_lengua',
        'lenguaje_estado',
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

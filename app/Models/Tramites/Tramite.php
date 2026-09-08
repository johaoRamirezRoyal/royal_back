<?php

namespace App\Models\Tramites;

use App\Casts\NullIfZeroString;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Tramite extends Model
{
    protected $table = 'tramite';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'tipo_tramite',
        'motivo',
        'mencion_certificado',
        'otra',
        'entidad_certificado',
        'correo',
        'modo_entrega',
        'anio_grabable',
        'eps_actual',
        'eps_traslado',
        'eps_grupo',
        'eps_grupo_familiar',
        'beneficiario',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'motivo_rechazo',
        'id_log',
        'id_edit',
        'fecha_edit',
    ];

    protected $casts = [
        // El legado insertaba '0' literal en estas columnas cuando no aplicaban para el
        // tipo de trámite elegido (ver ControlRecursos::solicitarTramiteControl) — filas
        // viejas mostrarían "0" en vez de quedar vacías sin este cast.
        'motivo' => NullIfZeroString::class,
        'mencion_certificado' => NullIfZeroString::class,
        'otra' => NullIfZeroString::class,
        'entidad_certificado' => NullIfZeroString::class,
        'correo' => NullIfZeroString::class,
        'modo_entrega' => NullIfZeroString::class,
        'anio_grabable' => NullIfZeroString::class,
        'id_user' => 'integer',
        'tipo_tramite' => 'integer',
        'eps_actual' => 'integer',
        'eps_traslado' => 'integer',
        'eps_grupo' => 'integer',
        'eps_grupo_familiar' => 'integer',
        'beneficiario' => 'integer',
        'estado' => 'integer',
        'id_log' => 'integer',
        'id_edit' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fechareg' => 'datetime',
        'fecha_edit' => 'datetime',
    ];

    protected $attributes = ['estado' => 0];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function tipo()
    {
        return $this->belongsTo(TramiteTipo::class, 'tipo_tramite', 'id');
    }

    public function documentos()
    {
        return $this->hasMany(TramiteDocumento::class, 'id_tramite', 'id');
    }

    public function familiares()
    {
        return $this->hasMany(TramiteFamiliar::class, 'id_tramite', 'id');
    }
}

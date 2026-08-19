<?php

namespace App\Models\ProcesoCompra\Solicitudes;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolicitudVerificacion extends Model
{
    protected $table = 'solicitud_verificacion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'cantidad',
        'observacion_cant',
        'calidad',
        'observacion_calidad',
        'precios',
        'observacion_precios',
        'plazos',
        'observacion_plazo',
        'id_log',
        'factura_doc',
        'fecha_verificacion',
    ];

    protected $casts = [
        'id_solicitud' => 'integer',
        'id_log' => 'integer',
        'fecha_verificacion' => 'date',
        'fechareg' => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_log', 'id_user');
    }
}
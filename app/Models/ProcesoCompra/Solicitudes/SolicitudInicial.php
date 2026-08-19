<?php

namespace App\Models\ProcesoCompra\Solicitudes;

use App\Models\ProcesoCompra\Proveedores\ProveedorDetalle;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolicitudInicial extends Model
{
    protected $table = 'solicitudes_inicial';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'id_area',
        'fecha_solicitud',
        'justificacion',
        'id_log',
        'estado',
        'observacion',
        'iva',
        'id_proveedor',
        'fecha_aplazado',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'id_area' => 'integer',
        'id_log' => 'integer',
        'estado' => 'integer',
        'id_proveedor' => 'integer',
        'fecha_solicitud' => 'date',
        'fecha_aplazado' => 'date',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'estado' => 0,
        'id_proveedor' => 0,
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function proveedor()
    {
        return $this->belongsTo(ProveedorDetalle::class, 'id_proveedor', 'id_proveedor');
    }

    public function productos()
    {
        return $this->hasMany(SolicitudProductoInicial::class, 'id_solicitud');
    }

    public function verificacionInicial()
    {
        return $this->hasOne(SolicitudVerificacionInicial::class, 'id_solicitud');
    }
}
<?php

namespace App\Models\ProcesoCompra\Proveedores;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ProveedorDetalle extends Model
{
    protected $table = 'proveedor_detalle';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nombre',
        'identificacion',
        'num_identificacion',
        'direccion',
        'ciudad',
        'departamento',
        'pais',
        'telefono',
        'correo',
        'fecha_ingreso',
        'tipo',
        'tiempo_entrega',
        'garantia',
        'plazo_pago',
        'detalle_producto',
        'nom_representante',
        'identificacion_representante',
        'correo_representante',
        'telefono_representante',
        'regimen_proveedor',
        'contribuyente_proveedor',
        'autoretenedor_proveedor',
        'comercio_proveedor',
        'actividad_proveedor',
        'tarifa_proveedor',
        'comercial_nombre',
        'identificacion_comercial',
        'correo_comercial',
        'telefono_comercial',
        'direccion_comercial',
        'ciudad_comercial',
        'departamento_comercial',
        'id_log',
    ];

    protected $casts = [
        'id_proveedor' => 'integer',
        'id_log' => 'integer',
        'fecha_ingreso' => 'date',
        'fechareg' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_proveedor', 'id_user');
    }

    public function documentos()
    {
        return $this->hasMany(ProveedorDocumento::class, 'id_proveedor', 'id_proveedor');
    }

    public function contactos()
    {
        return $this->hasMany(ProveedorContacto::class, 'id_proveedor', 'id_proveedor');
    }

    public function bancos()
    {
        return $this->hasMany(ProveedorBanco::class, 'id_proveedor', 'id_proveedor');
    }
}
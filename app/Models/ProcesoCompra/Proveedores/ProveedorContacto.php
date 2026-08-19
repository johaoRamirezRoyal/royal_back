<?php

namespace App\Models\ProcesoCompra\Proveedores;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ProveedorContacto extends Model
{
    protected $table = 'proveedor_contactos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nombre_contacto',
        'telefono_contacto',
        'correo_contacto',
        'cargo_contacto',
        'activo',
        'id_log',
    ];

    protected $casts = [
        'id_proveedor' => 'integer',
        'activo' => 'integer',
        'id_log' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function proveedor()
    {
        return $this->belongsTo(ProveedorDetalle::class, 'id_proveedor', 'id_proveedor');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_log', 'id_user');
    }
}
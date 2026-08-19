<?php

namespace App\Models\ProcesoCompra\Proveedores;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ProveedorBanco extends Model
{
    protected $table = 'proveedor_banco';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nom_banco',
        'num_banco',
        'tipo_cuenta',
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
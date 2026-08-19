<?php

namespace App\Models\ProcesoCompra\Proveedores;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ProveedorDocumento extends Model
{
    protected $table = 'proveedor_documento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'nombre',
        'tipo_documento',
        'activo',
        'id_log',
    ];

    protected $casts = [
        'id_proveedor' => 'integer',
        'tipo_documento' => 'integer',
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

    public function tipoDocumento(){
        return $this->belongsTo(TipoDocumentoProveedor::class, 'tipo_documento', 'id');
    }
}
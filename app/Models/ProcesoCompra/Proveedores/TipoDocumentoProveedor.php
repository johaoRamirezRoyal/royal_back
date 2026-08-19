<?php

namespace App\Models\ProcesoCompra\Proveedores;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class TipoDocumentoProveedor extends Model
{
    protected $table = 'tipo_documento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
        'id_super_empresa',
    ];

    protected $casts = [
        'activo' => 'integer',
        'id_super_empresa' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function documentos()
    {
        return $this->hasMany(ProveedorDocumento::class, 'tipo_documento', 'id');
    }

    public function superEmpresa()
    {
        return $this->belongsTo(Usuario::class, 'id_super_empresa', 'id_user');
    }
}
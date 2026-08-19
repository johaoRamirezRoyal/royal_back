<?php

namespace App\Models\ProcesoCompra\Solicitudes;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolicitudProducto extends Model
{
    protected $table = 'solicitud_productos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'producto',
        'cantidad',
        'precio',
        'iva',
        'id_log',
    ];

    protected $casts = [
        'id_solicitud' => 'integer',
        'id_log' => 'integer',
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
<?php

namespace App\Models\ProcesoCompra\Solicitudes;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolCertificado extends Model
{
    protected $table = 'sol_certificados';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'lugar',
        'cargo',
        'nombre_entidad',
        'trabaja_act',
        'tipo_cert',
        'anio',
        'estado',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'tipo_cert' => 'integer',
        'estado' => 'integer',
        'id_super_empresa' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'estado' => 1,
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

}
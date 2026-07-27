<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ExperienciaLaboral extends Model
{
    protected $table = 'experiencia_laboral';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre_empresa',
        'cargo',
        'fecha_ingreso',
        'fecha_retiro',
        'id_user',
        'certificado_trabajo',
        'fechareg',
        'fecha_certificado',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'fecha_ingreso' => 'date',
        'fecha_retiro' => 'date',
        'fechareg' => 'datetime',
        'fecha_certificado' => 'date',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

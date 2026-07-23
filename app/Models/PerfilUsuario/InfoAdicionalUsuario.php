<?php

namespace App\Models\PerfilUsuario;

use App\Models\TipoDocumento\TipoDocumento;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class InfoAdicionalUsuario extends Model
{
    protected $table = 'info_adicional_usuarios';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tipo_documento',
        'fecha_expedicion',
        'departamento_nacimiento',
        'fecha_nacimiento',
        'direccion_vivienda',
        'genero',
        'ultimo_nivel_educativo',
        'correo_personal',
        'estrato',
        'id_user',
        'fecha_reg',
        'cedula_doc',
    ];
    protected $casts = [
        'fecha_expedicion' => 'date',
        'fecha_nacimiento' => 'date',
        'fecha_reg' => 'datetime',
        'estrato' => 'integer',
        'id_user' => 'integer',
    ];

    /** * Usuario al que pertenece la información adicional. */ 
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento', 'id');
    }
}

<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class CertificadoFormacion extends Model
{
    protected $table = 'certificado_formacion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_formacion',
        'id_user',
        'nombre_archivo',
        'fechareg',
    ];

    protected $casts = [
        'id_formacion' => 'integer',
        'id_user' => 'integer',
        'fechareg' => 'datetime',
    ];

    public function formacion()
    {
        return $this->belongsTo(Formacion::class, 'id_formacion', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

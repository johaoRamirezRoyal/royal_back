<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Formacion extends Model
{
    protected $table = 'formacion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'tipo_formacion',
        'programa',
        'institucion',
        'fecha_grado',
        'fecha_expedicion_certi',
        'duracion',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'duracion' => 'integer',
        'fecha_grado' => 'date',
        'fecha_expedicion_certi' => 'date',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function certificados()
    {
        return $this->hasMany(CertificadoFormacion::class, 'id_formacion', 'id');
    }
}

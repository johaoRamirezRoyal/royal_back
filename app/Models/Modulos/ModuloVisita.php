<?php

namespace App\Models\Modulos;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ModuloVisita extends Model
{
    protected $table = 'modulo_visitas';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'modulo',
    ];

    protected $dates = ['fechareg'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_user');
    }
}

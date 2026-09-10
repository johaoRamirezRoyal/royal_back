<?php

namespace App\Models\Areas;

use App\Models\Inventario\Inventario;
use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Bloque extends Model
{
    protected $table = "bloques";

    protected $fillable = [
        'nombre',
        'id_nivel',
        'activo',
        'user_log',
        'fechareg',
    ];

    protected $dates = ['fechareg'];

    public $timestamps = false;

    protected $attributes = [
        'activo' => 1,
    ];

    public function nivel(){
        return $this->belongsTo(Nivel::class, 'id_nivel', 'id');
    }

    public function areas(){
        return $this->hasMany(Areas::class, 'id_bloque');
    }

    public function inventarioDirecto(){
        return $this->hasMany(Inventario::class, 'id_bloque');
    }

    public function responsables(){
        return $this->belongsToMany(Usuario::class, 'bloque_usuario', 'id_bloque', 'id_user')
            ->withPivot('fechareg');
    }
}

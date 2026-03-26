<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Nivel extends Model
{
    protected $table = "nivel";

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id', 
        'nombre',
        'activo',
        'user_log',
        'fechareg'
    ];

    public function perfil(){
        return $this->hasMany(Usuario::class, 'id_nivel');
    }

}
<?php
namespace App\Models\Usuarios;

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
        'fechareg',
        'id_nivel_academico',
    ];

    public function perfil(){
        return $this->hasMany(Usuario::class, 'id_nivel');
    }

    public function nivelAcademico()
    {
        return $this->belongsTo(NivelAcademico::class, 'id_nivel_academico');
    }

}
<?php

namespace App\Models\PermisosAutorizaciones;

use App\Models\Usuarios\Perfil;
use Illuminate\Database\Eloquent\Model;

class Opcion extends Model
{
    protected $table = "cron_opciones";

    public $timestamps = false; 

    protected $fillable = ['nombre', 'id_modulo', 'activo'];

    public function modulo(){
        return $this->belongsTo(Modulo::class, 'id_modulo');
    }

    public function permisos(){
        return $this->hasMany(Permiso::class, 'id_opcion');
    }

    public function perfiles(){
        return $this->belongsToMany(
                Perfil::class, 'cron_permisos',
                'id_opcion',
                'id_perfil'
            )->withPivot('activo', 'user_log', 'fechareg');
    }
}
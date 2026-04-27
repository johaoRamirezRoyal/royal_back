<?php
namespace App\Models\PermisosAutorizaciones;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model 
{
    protected $table = "cron_permisos";

    public $timestamps = false;

    protected $fillable = ['id_opcion', 'id_perfil', 'id_log', 'activo'];

    public function opcion(){
        return $this->belongsTo(Opcion::class, 'id_opcion');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model 
{
    protected $table = "cron_modulos";

    public $timestamps = false;

    protected $fillable = ['nombre', 'activo'];

    public function opciones(){
        return $this->hasMany(Opcion::class, 'id_modulo');
    }
}
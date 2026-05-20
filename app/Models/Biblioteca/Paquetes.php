<?php

namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;

class Paquetes extends Model
{
    protected $table = "biblioteca_paquete";

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'id_log',
        'activo',
        'estado',
        'fechareg'
    ];

    public function contenidos(){
        return $this->hasMany(PaqueteContenido::class, 'id_paquete');
    }

    public function prestamos(){
        return $this->hasMany(PaquetePrestamos::class, 'id_paquete');
    }
}

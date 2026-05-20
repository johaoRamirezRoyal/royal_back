<?php

namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;

class PaqueteContenido extends Model
{
    protected $table = "biblioteca_paquete_contenido";

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'id_paquete',
        'codigo',
        'id_log',
        'activo',
        'fechareg',
        'id_inactivo',
        'fecha_inactivo'
    ];

    public function paquete(){
        return $this->belongsTo(Paquetes::class, 'id_paquete');
    }
}
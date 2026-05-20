<?php

namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;

class Ejemplares extends Model
{
    protected $table = 'biblioteca_ejemplares';

    public $timestamps = false;

    protected $fillable = [
        'id_libro',
        'codigo',
        'estado',
        'id_log',
        'fechareg',
        'fecha_inactivo'
    ];

    public function libro(){
        return $this->belongsTo(Libro::class, 'id_libro');
    }

    public function prestamos(){
        return $this->hasMany(PrestamosEjemplar::class, 'id_ejemplar');
    }
}

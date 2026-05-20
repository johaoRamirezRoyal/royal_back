<?php

namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;

class Libro extends Model
{
    protected $table = "biblioteca_libros";

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'autor',
        'editorial',
        'edicion',
        'id_categoria',
        'id_subcategoria',
        'observacion',
        'foto',
        'id_log',
        'activo',
        'fechareg',
        'id_log_act',
        'fecha_actualizacion'
    ];

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function subcategoria(){
        return $this->belongsTo(Subcategoria::class, 'id_subcategoria');
    }

    public function ejemplares(){
        return $this->hasMany(Ejemplares::class, 'id_libro');
    }
}

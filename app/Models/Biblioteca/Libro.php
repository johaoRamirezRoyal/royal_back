<?php

namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivos(Builder $query)
    {
        return $query->where('activo', 1);
    }

    public function scopeCategoria(Builder $query, int $id_categoria)
    {
        return $query->where(
            'id_categoria',
            $id_categoria
        );
    }

    public function scopeAutor(Builder $query, string $autor)
    {
        return $query->where(
            'autor',
            'like',
            "%{$autor}%"
        );
    }

}

<?php
namespace App\Models\Biblioteca;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Categoria extends Model
{
    protected $table = "biblioteca_categorias";

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'clasificacion',
        'color',
        'activo',
        'id_log',
        'fechareg',
    ];

    public function subcategorias(){
        return $this->hasMany(Subcategoria::class, 'id_categoria');
    }

    public function libros(){
        return $this->hasMany(Libro::class, 'id_categoria');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivo(Builder $query){
        return $query->where("activo", 1);
    }
}
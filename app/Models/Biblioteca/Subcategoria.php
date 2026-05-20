<?php
namespace App\Models\Biblioteca;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Subcategoria extends Model
{
    protected $table = "biblioteca_subcategoria";

    public $timestamps = false;

    protected $fillable = [
        'id_categoria',
        'nombre',
        'activo',
        'id_log',
        'fechareg'
    ];

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function libros(){
        return $this->hasMany(Libro::class, 'id_subcategoria');
    }

    public function scopeActivo(Builder $query)
    {
        return $query->where("activo", 1);
    }

    public function scopeCategoria(Builder $query, ?int $id_categoria = null)
    {
        return $query->when($id_categoria, fn(Builder $q) => $q->where('id_categoria', $id_categoria));
    }
}
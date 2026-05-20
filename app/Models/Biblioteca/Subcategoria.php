<?php
namespace App\Models\Biblioteca;


use Illuminate\Database\Eloquent\Model;

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
}
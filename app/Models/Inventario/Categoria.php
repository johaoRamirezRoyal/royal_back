<?php 
namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = "categoria";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'tipo_categoria',
        'activo'
    ];

    public $timestamps = false;

    /** Catálogo de tipo_categoria (1=Sistemas, 2=Operativo, 3=Área Común) — no hay columna propia, la FK va sobre tipo_categoria mismo. */
    public function subcategoria(){
        return $this->belongsTo(SubcategoriaInventario::class, 'tipo_categoria', 'id');
    }
}
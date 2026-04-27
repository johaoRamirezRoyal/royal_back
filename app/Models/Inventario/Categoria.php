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
}
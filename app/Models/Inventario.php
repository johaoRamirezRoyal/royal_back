<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = "inventario";

    protected $primaryKey = 'id';

    protected $fillable = [
        'descripcion',
        'marca',
        'modelo',
        'precio',
        'estado',
        'activo',
        'fecha_compra',
        'observacion',
        'id_user',
        'id_area',
        'id_categoria',
        'user_log',
        'confirmado',
        'codigo',
        'id_compra',
        'detalles'
    ];

    protected $casts = [
        'precio' => 'float',
        'estado' => 'integer',
        'activo' => 'boolean',
        'confirmado' => 'boolean',
        'fecha_compra' => 'date',
    ];

    public $timestamps = false;

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function area(){
        return $this->belongsTo(Areas::class, 'id_area', 'id');
    }

    public function categoria(){
        return $this->belongTo(Categoria::class, 'id', 'id_categoria');
    }
}
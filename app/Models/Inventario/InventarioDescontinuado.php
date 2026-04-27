<?php
namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

//Modelo para el registro de movimientos de descontinuación de inventario
class InventarioDescontinuado extends Model
{
    protected $table = "inventario_desc";
    protected $primaryKey = 'id';
    protected $fillable = [
        "id_inventario",
        "id_log",
    ];

    protected $casts = [
        'fechareg' => 'datetime',
    ];
    public $timestamps = false;

    protected $defaultOrderBy = ['fechareg' => 'desc'];

    public function inventario(){
        return $this->belongsTo(Inventario::class, "id_inventario", "id");
    }
}
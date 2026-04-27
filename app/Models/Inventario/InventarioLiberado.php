<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioLiberado extends Model
{
    protected $table = "inventario_lib";

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_inventario',
        'id_log',
        'fechareg',
    ];

    protected $casts = [
        'id_inventario' => 'integer',
        'id_log' => 'integer',
        'fechareg' => 'date',
    ];

    public $timestamps = false;

    public function inventario(){
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id');
    }
}

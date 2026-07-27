<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioLog extends Model
{
    protected $table = 'inventario_log';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'id_user',
        'id_area',
        'id_log',
        'estado',
        'id_super_empresa',
        'fechareg',
    ];

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id');
    }
}

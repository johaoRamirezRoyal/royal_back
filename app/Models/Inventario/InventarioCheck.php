<?php

namespace App\Models\Inventario;

use App\Models\AnioEscolar\Anio;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Check semestral de un ítem de inventario de Área Común — migración del
 * "check de zonas" legacy (tabla chek_zonas). Ver AGENTS.md "Áreas Comunes".
 */
class InventarioCheck extends Model
{
    protected $table = "inventario_check";

    protected $fillable = [
        'id_inventario',
        'id_anio',
        'periodo',
        'id_user',
        'fechareg',
    ];

    public $timestamps = false;

    public function inventario(){
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id');
    }

    public function anioEscolar(){
        return $this->belongsTo(Anio::class, 'id_anio', 'id');
    }

    public function responsable(){
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

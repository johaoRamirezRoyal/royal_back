<?php 

namespace App\Models\Areas;

use App\Models\Inventario\Reportes;
use Illuminate\Database\Eloquent\Model;


class Areas extends Model
{

    protected $table = "areas";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'id_bloque',
        'user_log',
        'activo',
        'fechareg'
    ];

    protected $dates = ['fechareg'];

    public $timestamps = false;

    protected $attributes = [
        'activo' => 1
    ];


    public function reportes(){
        return $this->hasMany(Reportes::class, 'id_area');
    }

    public function bloque(){
        return $this->belongsTo(Bloque::class, 'id_bloque', 'id');
    }
}
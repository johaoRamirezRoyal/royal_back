<?php 

namespace App\Models\AnioEscolar;

use App\Models\Inventario\Reportes;
use Illuminate\Database\Eloquent\Model;

class Anio extends Model
{
    protected $table = "anio_escolar";

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'anio_inicio',
        'anio_fin',
        'id_log',
        'activo',
        'fechareg',
    ];

    protected $casts = [
        'anio_inicio' => 'integer',
        'anio_fin' => 'integer',
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    public function periodos(){
        return $this->hasMany(Periodo::class, 'id_anio');
    }

    public function reportes(){
        return $this->hasMany(Reportes::class, 'id_anio');
    }
}
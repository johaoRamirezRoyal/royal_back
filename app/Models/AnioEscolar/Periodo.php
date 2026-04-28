<?php

namespace App\Models\AnioEscolar;

use App\Models\Inventario\Reportes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    use HasFactory;

    protected $table = 'periodos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'numero',
        'id_anio',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'id_log',
        'fechareg',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function anioEscolar()
    {
        return $this->belongsTo(Anio::class, 'id_anio');
    }

    public function reportes(){
        return $this->hasMany(Reportes::class, 'periodo');
    }
}
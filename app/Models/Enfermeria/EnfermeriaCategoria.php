<?php

namespace App\Models\Enfermeria;

use Illuminate\Database\Eloquent\Model;

class EnfermeriaCategoria extends Model
{
    protected $table = 'enfermeria_categoria';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tratamiento_sugerido',
        'activo',
        'id_log',
        'fechareg',
    ];

    protected $casts = [
        'activo' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'activo' => 1,
    ];
}

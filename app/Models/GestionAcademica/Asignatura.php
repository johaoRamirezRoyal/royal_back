<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    protected $table = 'academico_asignatura';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'codigo',
        'abreviatura',
        'color',
        'activo',
    ];

    protected $attributes = [
        'activo' => 1,
    ];
}

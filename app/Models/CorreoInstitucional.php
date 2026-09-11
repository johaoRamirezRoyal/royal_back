<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorreoInstitucional extends Model
{
    protected $table = 'correos_institucionales';

    public $timestamps = false;

    protected $fillable = [
        'grupo',
        'nombre',
        'correo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}

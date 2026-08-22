<?php

namespace App\Models\AnioEscolar;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionAcademica extends Model
{
    protected $table = 'configuracion_academica';

    public $timestamps = false;

    protected $fillable = [
        'tipo_calendario',
    ];
}

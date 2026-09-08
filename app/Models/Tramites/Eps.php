<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

/** Tabla legacy preexistente (usada también por Matrícula/Admisiones) — sin migración propia, solo lectura acá. */
class Eps extends Model
{
    protected $table = 'eps';

    protected $primaryKey = 'id';

    public $timestamps = false;
}

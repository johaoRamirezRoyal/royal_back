<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

class ValoracionLogro extends Model
{
    protected $table = 'academico_valoracion_logro';

    public $timestamps = false;

    protected $fillable = ['id_valoracion', 'id_logro', 'id_escala'];
}

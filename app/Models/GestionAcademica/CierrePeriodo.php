<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

/** Cierre de un curso en un periodo por el coordinador: congela los boletines (academico_boletin). */
class CierrePeriodo extends Model
{
    protected $table = 'academico_cierre_periodo';

    public $timestamps = false;

    protected $fillable = ['id_curso', 'id_periodo_academico', 'id_usuario', 'fecha'];

    protected $casts = ['fecha' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_user');
    }
}

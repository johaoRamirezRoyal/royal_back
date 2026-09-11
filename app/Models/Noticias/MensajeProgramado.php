<?php

namespace App\Models\Noticias;

use App\Models\Usuarios\Nivel;
use Illuminate\Database\Eloquent\Model;

class MensajeProgramado extends Model
{
    protected $table = 'asistencia_mensaje';

    protected $fillable = [
        'fecha',
        'titulo',
        'imagen',
        'mensaje',
        'url',
        'nivel',
        'id_log',
        'activo',
    ];

    public $timestamps = false;

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    protected $attributes = [
        // 0 = todos los niveles (ver AGENTS.md "Noticias").
        'nivel' => 0,
        'activo' => 1,
    ];

    public function nivelRelacion()
    {
        return $this->belongsTo(Nivel::class, 'nivel', 'id');
    }
}

<?php

namespace App\Models;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogActividad extends Model
{
    protected $table = 'logs_actividad';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'metodo',
        'ruta',
        'status_code',
        'duracion_ms',
        'fechareg',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

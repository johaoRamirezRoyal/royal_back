<?php

namespace App\Models\Usuarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositivoConfiable extends Model
{
    protected $table = 'dispositivos_confiables';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'token_hash',
        'nombre_dispositivo',
        'ip_registro',
        'ultima_vez',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_user' => 'integer',
        'ultima_vez' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

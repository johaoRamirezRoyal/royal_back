<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Vive en `admin_management`, no en la base operativa — ver migración
 * create_llaves_maestras_table. Sin FK real a `usuarios.id_user` (el usuario destino
 * puede estar en cualquier tenant, ver `connection_objetivo`). */
class LlaveMaestra extends Model
{
    protected $connection = 'admin_management';

    protected $table = 'llaves_maestras';

    public $timestamps = false;

    protected $fillable = [
        'id_user_objetivo',
        'connection_objetivo',
        'generado_por',
        'token_hash',
        'usado_en',
        'ip_uso',
        'expira_en',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_user_objetivo' => 'integer',
        'generado_por' => 'integer',
        'usado_en' => 'datetime',
        'expira_en' => 'datetime',
        'created_at' => 'datetime',
    ];
}

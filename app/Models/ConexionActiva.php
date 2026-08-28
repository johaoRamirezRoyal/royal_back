<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Preferencia por Super Admin de qué connection usar como `database.default` — ver
 * App\Http\Middleware\SwitchActiveConnection. Vive en `admin_management`, no en la base
 * operativa. Sin FK real a `usuarios.id_user` (bases distintas, ver BaseDatosNombre/LogDominio). */
class ConexionActiva extends Model
{
    protected $connection = 'admin_management';

    protected $table = 'admin_conexion_activa';

    protected $primaryKey = 'id_user';

    public $incrementing = false;

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = 'fecha_updated';

    protected $fillable = [
        'id_user',
        'connection',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseDatosNombre extends Model
{
    /** Vive en `admin_management`, no en la base operativa (ver config/database.php). */
    protected $connection = 'admin_management';

    protected $table = 'bases_datos_nombres';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = 'fecha_updated';

    protected $fillable = [
        'connection',
        'nombre',
    ];
}

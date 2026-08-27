<?php

namespace App\Models;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogDominio extends Model
{
    /** Vive en `admin_management`, no en la base operativa (ver config/database.php). */
    protected $connection = 'admin_management';

    protected $table = 'logs_dominio';

    public $timestamps = false;

    protected $fillable = [
        'dominio',
        'ip',
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

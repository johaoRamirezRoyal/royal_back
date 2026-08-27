<?php

namespace App\Models;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogActividad extends Model
{
    /** Vive en la base operativa (`mysql`), no en `admin_management` — es auditoría propia
     * de ESTA base (FK real a usuarios.id_user), a diferencia de LogDominio, que es
     * transversal a todos los dominios/tenants y por eso sí vive en admin_management (ver
     * config/database.php). Cada base que se sume a futuro (por tenant/dominio) tendría su
     * propio log de actividad igual que este, no uno compartido. */
    protected $connection = 'mysql';

    protected $table = 'logs_actividad';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'ip',
        'pais',
        'metodo',
        'ruta',
        'status_code',
        'duracion_ms',
        'fechareg',
    ];

    protected $casts = [
        // Cifrado con APP_KEY (AES-256-CBC vía Illuminate\Encryption); se desencripta solo al acceder en PHP.
        'ip' => 'encrypted',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}

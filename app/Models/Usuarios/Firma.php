<?php

namespace App\Models\Usuarios;

use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Firma extends Model
{
    protected $table = 'firmas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nombre',
        'url',
        'activo',
        'user_log',
        'fechareg',
    ];

    protected $casts = [
        'fechareg' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    /**
     * Borra la fila y su archivo de Cloudinary si es una firma inactiva (manual de una historia
     * clínica o «Mi firma» reemplazada) que ya ninguna historia usa. La activa nunca se borra
     * aquí: es la de «Mi firma». Si Cloudinary falla, se registra y la fila se borra igual.
     */
    public function descartarSiNoSeUsa(): void
    {
        if ($this->activo || HceObservacionesFirmas::where('id_firma_psicologa', $this->id)->exists()) {
            return;
        }

        if ($this->url) {
            $resultado = app(CloudinaryService::class)->deleteFileByUrl($this->url);
            if ($resultado['error']) {
                Log::warning("No se pudo borrar de Cloudinary la firma {$this->id}: {$resultado['message']}");
            }
        }

        $this->delete();
    }
}

<?php

namespace App\Models\Usuarios;

use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Services\Cloudinary\ArchivosPrivados;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
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
     * Bytes de la firma activa del usuario («Mi firma», `url` de Cloudinary), o null si no tiene
     * o no se pudo leer: el documento sale igual, con la línea de firma en blanco. Las firmas del
     * legacy (solo `nombre`, sin `url`) ya no se leen: el usuario la vuelve a subir.
     */
    public static function contenidoActiva(?int $idUser): ?string
    {
        $url = $idUser ? self::where('id_user', $idUser)->where('activo', 1)->value('url') : null;
        if (! $url) {
            return null;
        }

        try {
            $res = Http::timeout(5)->get(ArchivosPrivados::firmar($url));

            return $res->successful() ? $res->body() : null;
        } catch (\Throwable $e) {
            Log::warning("No se pudo descargar la firma del usuario {$idUser}: ".$e->getMessage());

            return null;
        }
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

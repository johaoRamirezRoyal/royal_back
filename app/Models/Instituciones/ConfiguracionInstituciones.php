<?php

namespace App\Models\Instituciones;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionInstituciones extends Model
{
    public const ID_CONFIG = 1;

    protected $table = 'configuracion_instituciones';

    public $timestamps = false;

    protected $fillable = [
        'dias_plazo_bloqueo_correo',
        'correo_notificacion',
        'dominio_play_and_learn',
    ];

    protected $casts = [
        'dias_plazo_bloqueo_correo' => 'integer',
    ];

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }

    /** Correos de notificación parseados (separados por coma) — mismo formato que ya
     * usaba config/adminmanagement.php. */
    public function correosNotificacion(): array
    {
        if (! $this->correo_notificacion) return [];

        return array_filter(array_map(
            fn ($correo) => mb_strtolower(trim($correo)),
            explode(',', $this->correo_notificacion)
        ));
    }

    /** Si el dominio del correo coincide con `dominio_play_and_learn`, devuelve
     * "play_and_learn" para pre-asignarlo como tipo_documento; si no, null (no toca lo
     * que ya haya). No reemplaza el selector manual del admin, solo lo pre-completa al
     * registrar/verificar el correo — ver InstitucionController::verifyEmailOtp() y
     * InstitucionAdminController::store()/update(). */
    public function tipoDocumentoParaCorreo(string $email): ?string
    {
        if (! $this->dominio_play_and_learn) return null;

        $dominioCorreo = mb_strtolower(trim(substr($email, strrpos($email, '@') + 1)));
        $dominioConfigurado = mb_strtolower(trim($this->dominio_play_and_learn));

        return $dominioCorreo === $dominioConfigurado ? 'play_and_learn' : null;
    }
}

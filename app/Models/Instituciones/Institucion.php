<?php

namespace App\Models\Instituciones;

use App\Services\MailService;
use Illuminate\Database\Eloquent\Model;

class Institucion extends Model
{
    protected $table = 'instituciones';

    public const TIPOS_DOCUMENTO = ['coordinador_psicologo', 'play_and_learn'];

    protected $fillable = [
        'nombre',
        'tipo_documento',
        'nit',
        'email',
        'email_verified_at',
        'activo',
        'primer_ingreso_at',
        'ultima_ip',
    ];

    protected $hidden = [
        'nit',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'email_verified_at' => 'datetime',
        'primer_ingreso_at' => 'datetime',
    ];

    /** Cartas de recomendación (PDF) que la institución ha enviado desde su portal. */
    public function cartas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CartaRecomendacion::class, 'id_institucion');
    }

    public function fechaBloqueo(): ?\Illuminate\Support\Carbon
    {
        if (! $this->primer_ingreso_at) return null;

        // ponytail: una query por llamada, sin cache — la tabla de instituciones es
        // pequeña (jardines asociados), no vale la pena cachear esto todavía.
        $dias = ConfiguracionInstituciones::actual()->dias_plazo_bloqueo_correo;

        return $this->primer_ingreso_at->clone()->addDays($dias);
    }

    public function estaBloqueada(): bool
    {
        if ($this->email_verified_at) return false;

        $fechaBloqueo = $this->fechaBloqueo();

        return $fechaBloqueo !== null && now()->greaterThan($fechaBloqueo);
    }

    /**
     * Confirma al correo de la institución que quedó registrado: al verificarlo ella misma
     * (InstitucionController::verifyEmailOtp) o al crearla el admin con correo
     * (InstitucionAdminController::store). sendView no lanza: si falla, queda en el log.
     */
    public function enviarCorreoRegistrado(MailService $mail): void
    {
        $mail->sendView($this->email, 'Correo registrado — Admisiones ' . config('app.name'), 'emails.institucionCorreoRegistrado', [
            'nombreInstitucion' => $this->nombre,
            'email' => $this->email,
            'fecha' => now()->format('d/m/Y'),
        ]);
    }
}

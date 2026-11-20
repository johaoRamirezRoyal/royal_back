<?php

namespace App\Services\Acudientes;

use App\Enums\TipoPerfil;
use App\Mail\AccesoPortalPadresMail;
use App\Models\PasswordResetTokens;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\branding\ColegioAdmisionService;
use App\Services\MailService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Acudientes creados por el colegio (módulo /gestion-academica/acudientes): son `usuarios` de
 * tipo ACUDIENTE, los mismos que se registran solos en Admisiones y que entran al portal para
 * padres. El colegio nunca escribe ni ve la contraseña: la cuenta nace con una al azar y el
 * acudiente crea la suya con el enlace de «acceso al portal».
 */
class AcudientesService extends Service
{
    /** Días que dura el enlace de acceso al portal (el de recuperar contraseña dura 60 minutos). */
    public const DIAS_ENLACE_ACCESO = 7;

    public function __construct(
        private ColegioAdmisionService $colegios,
        private MailService $correo,
    ) {
    }

    /** @param array{documento: string, nombre: string, apellido: string, correo: string, telefono?: ?string} $datos */
    public function crear(array $datos, int $idUsuarioRegistra): array
    {
        try {
            Usuario::create([
                ...$this->limpiar($datos),
                'perfil' => Perfil::idParaCrear(TipoPerfil::ACUDIENTE),
                'estado' => 'activo',
                // Inservible para entrar: el acudiente crea la suya con el enlace de acceso.
                'pass' => Str::random(40),
                'user_log' => $idUsuarioRegistra,
            ]);

            return ['error' => false, 'message' => 'Acudiente creado', 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al crear el acudiente');
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            $acudiente = $this->buscar($id);
            if (! $acudiente) {
                return ['error' => true, 'message' => 'El acudiente no existe', 'data' => null];
            }

            $acudiente->update([...$this->limpiar($datos), 'fecha_editado' => now()]);

            return ['error' => false, 'message' => 'Acudiente actualizado', 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al actualizar el acudiente');
        }
    }

    /**
     * Envía al acudiente el enlace para crear su contraseña y entrar al portal de su colegio
     * (/{slug}/padres). Usa el mismo token y la misma página que recuperar contraseña por colegio,
     * pero el enlace dura DIAS_ENLACE_ACCESO días. Un enlace nuevo invalida el anterior.
     */
    public function enviarAccesoPortal(int $id): array
    {
        try {
            $acudiente = $this->buscar($id);
            if (! $acudiente) {
                return ['error' => true, 'message' => 'El acudiente no existe', 'data' => null];
            }
            if ($acudiente->estado !== 'activo') {
                return ['error' => true, 'message' => 'El acudiente está inactivo: actívalo antes de enviarle el acceso', 'data' => null];
            }

            $colegio = $this->colegios->colegioDeLaSesion();
            if (! $colegio) {
                return ['error' => true, 'message' => 'Este colegio no tiene enlace del portal para padres: créalo en Colegios', 'data' => null];
            }

            $token = Str::random(64);
            PasswordResetTokens::updateOrInsert(['email' => $acudiente->correo], [
                'token' => bcrypt($token),
                'used' => false,
                'created_at' => now(),
                'expires_at' => Carbon::now()->addDays(self::DIAS_ENLACE_ACCESO),
            ]);

            $frontend = rtrim(config('app.frontend_url'), '/');
            $url = "{$frontend}/password/restore?" . http_build_query(['token' => $token, 'email' => $acudiente->correo, 'colegio' => $colegio->slug]);
            $enviado = $this->correo->send($acudiente->correo, new AccesoPortalPadresMail(
                $acudiente->nombre,
                $acudiente->correo,
                $url,
                "{$frontend}/{$colegio->slug}/padres",
                self::DIAS_ENLACE_ACCESO,
                $colegio,
            ));

            if (! $enviado) {
                return ['error' => true, 'message' => 'No se pudo enviar el correo. Revisa la dirección e inténtalo de nuevo', 'data' => null];
            }

            return ['error' => false, 'message' => "Acceso enviado a {$acudiente->correo}", 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al enviar el acceso al portal');
        }
    }

    private function buscar(int $id): ?Usuario
    {
        return Usuario::where('id_user', $id)->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ACUDIENTE))->first();
    }

    /** Recorta espacios y deja el correo en minúsculas (el login lo compara tal cual). */
    private function limpiar(array $datos): array
    {
        return [
            'documento' => trim($datos['documento']),
            'nombre' => trim($datos['nombre']),
            'apellido' => trim($datos['apellido']),
            'correo' => mb_strtolower(trim($datos['correo'])),
            'telefono' => filled($datos['telefono'] ?? null) ? trim($datos['telefono']) : null,
        ];
    }
}

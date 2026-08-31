<?php

namespace App\Http\Controllers\Institucion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institucion\CartaRecomendacionRequest;
use App\Http\Requests\Institucion\InstitucionEmailRequest;
use App\Http\Requests\Institucion\InstitucionLoginOtpRequest;
use App\Http\Requests\Institucion\InstitucionLoginRequest;
use App\Http\Requests\Institucion\InstitucionOtpRequest;
use App\Http\Traits\HasAuthCookie;
use App\Mail\Instituciones\CartaRecomendacionMail;
use App\Models\Instituciones\CartaRecomendacion;
use App\Models\Instituciones\ConfiguracionInstituciones;
use App\Models\Instituciones\Institucion;
use App\Pdf\Instituciones\CartaRecomendacionPdfService;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstitucionController extends Controller
{
    use HasAuthCookie;

    public function __construct(
        private MailService $mail_service,
        private CloudinaryService $cloudinary_service,
    ) {
    }

    public function listar()
    {
        $instituciones = Institucion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return $this->success('Instituciones obtenidas', $instituciones);
    }

    /**
     * El NIT por sí solo otorga la sesión — la verificación de correo NO es requisito de
     * acceso (se pide después desde la página principal, ver check()/requestEmailOtp()),
     * EXCEPTO cuando la institución ya tiene correo verificado y el login llega desde una
     * IP distinta a la de su último inicio de sesión (`ultima_ip`): ahí sí se exige un
     * código enviado a ese correo antes de otorgar la sesión (ver iniciarVerificacionLogin/
     * verifyLoginOtp) — misma IP de siempre no vuelve a pedir nada.
     */
    public function login(InstitucionLoginRequest $request)
    {
        $idInstitucion = $request->id_institucion;
        $ip = $request->ip();

        $rateLimitKey = "institucion_login_{$ip}_{$idInstitucion}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(10));
        }

        if ($attempts > 5) {
            return $this->error('Demasiados intentos. Intenta de nuevo más tarde.', 429);
        }

        $institucion = Institucion::where('id', $idInstitucion)->where('activo', true)->first();

        if (! $institucion || ! Hash::check($request->nit, $institucion->nit)) {
            Log::warning('Intento de login de institución fallido', ['id_institucion' => $idInstitucion, 'ip' => $ip]);

            return $this->error('Institución o NIT incorrectos.', 401);
        }

        Cache::forget($rateLimitKey);

        if ($institucion->email_verified_at && $institucion->ultima_ip !== $ip) {
            return $this->iniciarVerificacionLogin($institucion);
        }

        return $this->otorgarSesion($institucion, $ip);
    }

    /**
     * Envía el código de verificación de un login desde una IP nueva. Token de corta
     * vida (15 min) que solo identifica la institución en pausa de verificación — el
     * código en sí vive en una entrada separada de 5 min, mismo patrón de dos niveles
     * que ya usa AdmissionsController para el flujo de acudiente.
     */
    private function iniciarVerificacionLogin(Institucion $institucion)
    {
        $rateLimitKey = "institucion_login_otp_send_{$institucion->id}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
        }

        $token = Str::random(64);
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("institucion_login_pending_{$token}", ['id' => $institucion->id], now()->addMinutes(15));
        Cache::put("institucion_login_otp_{$token}", ['code' => $code, 'id' => $institucion->id], now()->addMinutes(5));

        $this->mail_service->sendView($institucion->email, 'Nuevo inicio de sesión — Admisiones Royal School', 'emails.sendInstitucionOtp', [
            'verificationCode' => $code,
            'nombreInstitucion' => $institucion->nombre,
        ]);

        Log::info("Login desde IP nueva para institución {$institucion->id}, verificación enviada");

        return $this->success('Verificación requerida', [
            'requires_otp' => true,
            'token' => $token,
        ]);
    }

    public function resendLoginOtp(Request $request)
    {
        $request->validate(
            ['token' => 'required|string|size:64'],
            ['token.required' => 'El token es obligatorio.', 'token.size' => 'El token no es válido.']
        );

        $pending = Cache::get("institucion_login_pending_{$request->token}");

        if (! $pending) {
            return $this->error('Sesión inválida o expirada. Inicia sesión nuevamente.', 401);
        }

        $institucion = Institucion::find($pending['id']);

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        $rateLimitKey = "institucion_login_otp_send_{$institucion->id}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("institucion_login_otp_{$request->token}", ['code' => $code, 'id' => $institucion->id], now()->addMinutes(5));

        $this->mail_service->sendView($institucion->email, 'Nuevo inicio de sesión — Admisiones Royal School', 'emails.sendInstitucionOtp', [
            'verificationCode' => $code,
            'nombreInstitucion' => $institucion->nombre,
        ]);

        return $this->success('Código reenviado');
    }

    public function verifyLoginOtp(InstitucionLoginOtpRequest $request)
    {
        $token = $request->token;

        $key = "institucion_login_otp_{$token}";
        $attemptsKey = "institucion_login_otp_attempts_{$token}";

        $data = Cache::get($key);

        if (! $data) {
            return $this->error('Token inválido o expirado.', 400);
        }

        $attempts = Cache::increment($attemptsKey);

        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 5) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            Cache::forget("institucion_login_pending_{$token}");

            return $this->error('Demasiados intentos.', 429);
        }

        if ($data['code'] !== $request->code) {
            return $this->error('Código inválido.', 400);
        }

        $institucion = Institucion::find($data['id']);

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);
        Cache::forget("institucion_login_pending_{$token}");

        return $this->otorgarSesion($institucion, $request->ip());
    }

    private function otorgarSesion(Institucion $institucion, string $ip)
    {
        $data = ['ultima_ip' => $ip];

        // El plazo de gracia para registrar el correo (ver Institucion::estaBloqueada)
        // arranca en el primer login exitoso, no en la creación del registro.
        if (! $institucion->primer_ingreso_at) {
            $data['primer_ingreso_at'] = now();
        }

        $institucion->update($data);

        $sessionToken = Str::random(64);
        // En producción la sesión dura 15 minutos (a pedido explícito); fuera de
        // producción se mantiene la ventana larga de 12h para no entorpecer pruebas.
        $expiresAt = app()->environment('production') ? now()->addMinutes(15) : now()->addHours(12);

        // `expires_at` va dentro del propio valor cacheado (no solo como TTL del store)
        // para poder devolvérselo al frontend — Cache::get() no expone el TTL restante.
        Cache::put("institucion_session_{$sessionToken}", [
            'id' => $institucion->id,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $this->success('Sesión iniciada', ['redirect' => true])
            ->withCookie($this->makeCookie($sessionToken, 'institucion_token'));
    }

    public function check(Request $request)
    {
        $institucion = Institucion::find($request->attributes->get('institucion_id'));

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        $pending = Cache::get("institucion_email_otp_{$institucion->id}");

        return $this->success('Sesión activa', [
            'institucion' => [
                'id' => $institucion->id,
                'nombre' => $institucion->nombre,
            ],
            'tipo_documento' => $institucion->tipo_documento,
            'email_verified' => ! is_null($institucion->email_verified_at),
            'otp_pending_email' => $pending['email'] ?? null,
            'bloqueada' => $institucion->estaBloqueada(),
            'bloqueo_fecha' => $institucion->fechaBloqueo()?->toIso8601String(),
            'session_expires_at' => $request->attributes->get('institucion_session_expires_at'),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->cookie('institucion_token');

        if ($token) {
            Cache::forget("institucion_session_{$token}");
        }

        return $this->success('Sesión cerrada correctamente')
            ->withCookie(cookie()->forget('institucion_token'));
    }

    /**
     * Registro del correo único de la institución — ya autenticada por NIT. Solo aplica
     * para el primer registro: si ya tiene un correo verificado, se rechaza (cambiarlo es
     * un caso aparte, no cubierto acá).
     */
    public function requestEmailOtp(InstitucionEmailRequest $request)
    {
        $institucion = Institucion::find($request->attributes->get('institucion_id'));

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        if ($institucion->email_verified_at) {
            return $this->error('Esta institución ya tiene un correo registrado.', 409);
        }

        $email = strtolower($request->email);

        $emailEnUso = Institucion::where('email', $email)->where('id', '!=', $institucion->id)->exists();

        if ($emailEnUso) {
            return $this->error('Este correo ya está registrado para otra institución.', 422);
        }

        $rateLimitKey = "institucion_send_{$email}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("institucion_email_otp_{$institucion->id}", [
            'code' => $code,
            'email' => $email,
        ], now()->addMinutes(5));

        $this->mail_service->sendView($email, 'Verifica el correo de tu institución — Admisiones Royal School', 'emails.sendInstitucionOtp', [
            'verificationCode' => $code,
            'nombreInstitucion' => $institucion->nombre,
        ]);

        Log::info("Enviando código de verificación a institución {$institucion->id} ({$email})");

        return $this->success('Código enviado');
    }

    public function verifyEmailOtp(InstitucionOtpRequest $request)
    {
        $institucionId = $request->attributes->get('institucion_id');

        $key = "institucion_email_otp_{$institucionId}";
        $attemptsKey = "institucion_email_otp_attempts_{$institucionId}";

        $data = Cache::get($key);

        if (! $data) {
            return $this->error('No hay una verificación de correo pendiente.', 400);
        }

        $attempts = Cache::increment($attemptsKey);

        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 5) {
            Cache::forget($key);
            Cache::forget($attemptsKey);

            return $this->error('Demasiados intentos.', 429);
        }

        if ($data['code'] !== $request->code) {
            return $this->error('Código inválido.', 400);
        }

        $institucion = Institucion::find($institucionId);

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);

        $update = [
            'email' => $data['email'],
            'email_verified_at' => now(),
        ];

        // Si el dominio del correo coincide con el de Play and Learn (configurable, ver
        // ConfiguracionInstituciones), pre-asigna ese tipo de documento — el admin lo
        // puede corregir después desde el selector manual si hace falta.
        if ($tipoDocumento = ConfiguracionInstituciones::actual()->tipoDocumentoParaCorreo($data['email'])) {
            $update['tipo_documento'] = $tipoDocumento;
        }

        $institucion->update($update);

        return $this->success('Correo registrado correctamente');
    }

    public function guardarCartaRecomendacion(CartaRecomendacionRequest $request)
    {
        $institucion = Institucion::find($request->attributes->get('institucion_id'));

        if (! $institucion) {
            return $this->error('Institución no encontrada.', 404);
        }

        if ($institucion->estaBloqueada()) {
            return $this->error('Tu cuenta está bloqueada temporalmente por no registrar el correo. Regístralo para reactivarla.', 403);
        }

        $carta = CartaRecomendacion::create([
            'id_institucion' => $institucion->id,
            'idioma' => $request->idioma,
            'datos' => $request->datos,
        ]);

        $this->generarSubirYNotificar($carta->fresh());

        return $this->success('Carta de recomendación enviada', $carta->fresh(), 201);
    }

    public function listarMisCartas(Request $request)
    {
        $cartas = CartaRecomendacion::where('id_institucion', $request->attributes->get('institucion_id'))
            ->orderByDesc('created_at')
            ->get(['id', 'idioma', 'datos', 'documento_url', 'created_at']);

        return $this->success('Cartas obtenidas', $cartas);
    }

    /**
     * Genera el PDF, lo sube a Cloudinary y envía copia al correo configurado
     * (ConfiguracionInstituciones::actual()->correosNotificacion(), editable desde el
     * admin) — se llama después de guardar la carta, no dentro de una transacción: un
     * fallo acá no debe impedir que la carta ya enviada quede registrada, solo se deja
     * constancia en el log (mismo patrón que EvaluacionesServices::enviarCorreoRespuesta).
     */
    private function generarSubirYNotificar(CartaRecomendacion $carta): void
    {
        $tmpPath = null;

        try {
            $pdfBinario = app(CartaRecomendacionPdfService::class)->generate($carta);
            $nombrePdf = 'carta-recomendacion-' . $carta->id . '.pdf';

            $tmpPath = tempnam(sys_get_temp_dir(), 'carta_') . '.pdf';
            file_put_contents($tmpPath, $pdfBinario);

            $archivo = new UploadedFile($tmpPath, $nombrePdf, 'application/pdf', null, true);
            $resultado = $this->cloudinary_service->uploadFile($archivo, 'Instituciones/CartasRecomendacion');

            if (! $resultado['error']) {
                $carta->update([
                    'documento_url' => $resultado['data']['url'],
                    'documento_public_id' => $resultado['data']['public_id'],
                ]);
            } else {
                Log::error('No se pudo subir la carta de recomendación a Cloudinary', [
                    'id_carta' => $carta->id,
                    'error' => $resultado['message'],
                ]);
            }

            $correos = ConfiguracionInstituciones::actual()->correosNotificacion();
            if (! empty($correos)) {
                $this->mail_service->send($correos, new CartaRecomendacionMail($carta, $pdfBinario, $nombrePdf));
            }
        } catch (\Throwable $e) {
            Log::error('No se pudo generar/subir/notificar la carta de recomendación', [
                'id_carta' => $carta->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($tmpPath && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}

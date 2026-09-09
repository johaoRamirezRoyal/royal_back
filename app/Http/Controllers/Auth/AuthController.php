<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Usuarios\UsuarioResource;
use App\Http\Traits\HasAuthCookie;
use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\LlaveMaestraService;
use App\Services\Auth\AuthServices;
use App\Services\branding\MarcaDominioService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\Sami\SamiSsoService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use HasAuthCookie;

    protected $service_usuarios;

    protected $service_auth;

    protected MailService $mail_service;

    public function __construct(
        UsuariosServices $usuariosServices,
        AuthServices $service_auth,
        private JwtService $jwt,
        private SamiSsoService $samiSso,
        private MarcaDominioService $marcaDominioService,
        private LlaveMaestraService $llaveMaestra,
        MailService $mail_service,
    ) {
        $this->service_usuarios = $usuariosServices;
        $this->service_auth = $service_auth;
        $this->mail_service = $mail_service;
    }

    /**
     * Vista previa (pre-login, sin token) de nombre/color de marca para un correo o
     * dominio — usada por la pantalla de Login para variar su identidad visual según el
     * último dominio ingresado, antes de que exista sesión (useMarcaColor.hook.ts hace lo
     * mismo pero post-login, leyendo el JWT). Reutiliza
     * MarcaDominioService::resolverPorCorreo (mismo método que UsuarioResource), que ya
     * acepta tanto un correo completo como un dominio suelto. Público a propósito — solo
     * expone nombre/color de marca, ya visible para cualquier usuario autenticado de ese
     * dominio, con rate limit básico por IP para evitar scraping.
     */
    public function brandingPreview(Request $request)
    {
        $request->validate(
            ['correo' => 'required|string|max:190'],
            ['correo.required' => 'El correo o dominio es obligatorio.']
        );

        $ip = $request->ip();
        $rateLimitKey = "branding_preview_{$ip}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinute());
        }

        if ($attempts > 60) {
            return response()->json(['error' => true, 'message' => 'Demasiadas solicitudes.'], 429);
        }

        $marca = $this->marcaDominioService->resolverPorCorreo($request->correo);

        return response()->json([
            'error' => false,
            'data' => [
                'nombre_marca' => $marca['nombre'],
                'descripcion_marca' => $marca['descripcion'],
                'color_marca' => $marca['color'],
                'logo_marca' => $marca['url'],
            ],
        ]);
    }

    // ===== GOOGLE OAUTH CONFIG (BEGIN) =====
    public function redirectToGoogle()
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->stateless()->redirect();
    }

    public function callbackGoogle()
    {
        try {
            /** @var GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            // Validar dominio institucional
            if (! str_ends_with($googleUser->getEmail(), '@royalschool.edu.co')) {
                return redirect("{$frontendUrl}/?error=dominio_invalido");
            }

            $usuarioResult = $this->service_auth->buscarUsuarioPorEmail($googleUser->getEmail());

            if ($usuarioResult && $usuarioResult['success']) {
                // Usuario existente
                $userModel = $usuarioResult['data'];
            } else {
                // Usuario nuevo
                $data_usuario = [
                    'documento' => null,
                    'nombre' => $googleUser->getName(),
                    'apellido' => null,
                    'correo' => $googleUser->getEmail(),
                    'telefono' => null,
                    'asignatura' => null,
                    'user' => $googleUser->getEmail(),
                    'pass' => Hash::make(uniqid()),
                    'perfil' => 10,
                    'id_nivel' => 1,
                    'fechareg' => now(),
                    'estado' => 'activo',
                ];

                $result = $this->service_auth->registrarUsuario($data_usuario);

                if (! $result['success']) {
                    return redirect("{$frontendUrl}/?error=usuario_inactivo");
                }

                $userModel = $result['data'];
            }

            // Validar estado
            if ($userModel->estado !== 'activo') {
                return redirect("{$frontendUrl}/?error=usuario_inactivo");
            }

            return redirect("{$frontendUrl}/home")->withCookie("token", $this->jwt->generateToken($userModel));

        } catch (\Exception $e) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            return redirect("{$frontendUrl}/?error=google_auth_failed");
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'documento' => 'required|numeric|unique:usuarios,documento',
            'nombre' => 'required|string',
            'apellido' => 'nullable|string',
            'correo' => 'required|email|ends_with:@royalschool.edu.co|unique:usuarios,correo',
            'user' => 'required|string|unique:usuarios,user',
            'pass' => 'required|string|min:6',
            'perfil' => 'required|integer',
            'id_nivel' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors(),
            ], 422);
        }

        $data_usuario = [
            'documento' => $request->documento,
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'telefono' => $request->telefono ?? null,
            'asignatura' => $request->asignatura ?? null,
            'user' => $request->user,
            'pass' => Hash::make($request->pass),
            'perfil' => $request->perfil,
            'id_nivel' => $request->id_nivel,
            'fechareg' => now(),
            'estado' => 'activo',
        ];

        $response = $this->service_auth->registrarUsuario($data_usuario);

        if (! $response) {
            return response()->json([
                'error' => true,
                'message' => 'No se pudo registrar el usuario',
            ], 500);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario creado correctamente',
            'data' => $response,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user' => 'required|string',
            'pass' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors(),
            ], 422);
        }

        // El identificador acepta usuario, correo o documento — busca entre todas las bases
        // con tabla `usuarios` (ver BasesDatosService::connectionsConUsuarios). El estado
        // 'activo' ya se valida ahí mismo (no hay una comprobación aparte después).
        $resultado = $this->service_auth->resolverUsuarioMultiTenant($request->user, $request->pass);

        if (! $resultado) {
            return response()->json([
                'error' => true,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        ['usuario' => $usuario, 'connection' => $connection] = $resultado;

        // A partir de acá el resto de la petición (y el JWT que se genera) queda anclado a
        // la base del tenant al que pertenece este usuario.
        config(['database.default' => $connection]);

        // Equipo ya verificado antes (cookie `device_token` con match en
        // dispositivos_confiables) → sesión directa, mismo flujo de siempre. Sin cookie o
        // sin match, se exige el código por correo antes de otorgar sesión — ver
        // verifyLoginOtp().
        $deviceToken = $request->cookie('device_token');

        if ($deviceToken && $this->service_auth->dispositivoEsConfiable($connection, $usuario->id_user, $deviceToken)) {
            // 🔹 Iniciar sesión silenciosa en SAMI (no bloqueante si falla)
            $this->samiSso->iniciarSesion($usuario->id_user, $usuario->user, $request->pass);

            return response()
                ->json(['Message' => 'Login Exitoso'])
                ->withCookie('token', $this->jwt->generateToken($usuario, $connection));
        }

        return $this->iniciarVerificacionLoginGeneral($usuario, $connection, $request);
    }

    /**
     * Envía el código de verificación de un login desde un equipo nuevo (sin cookie
     * `device_token` reconocida). Mismo patrón de dos entradas de Cache que
     * AdmissionsController::iniciarVerificacionLoginAcudiente: un token de pausa (15 min)
     * que solo identifica usuario+tenant, y el código en sí (5 min) aparte.
     */
    private function iniciarVerificacionLoginGeneral(Usuario $usuario, string $connection, Request $request)
    {
        $rateLimitKey = "auth_login_otp_send_{$usuario->id_user}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return response()->json(['error' => true, 'message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.'], 429);
        }

        $token = Str::random(64);
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("auth_login_pending_{$token}", ['id' => $usuario->id_user, 'connection' => $connection], now()->addMinutes(15));
        Cache::put("auth_login_otp_{$token}", ['code' => $code, 'id' => $usuario->id_user], now()->addMinutes(5));

        $this->mail_service->sendView($usuario->correo, 'Nuevo inicio de sesión — ' . config('app.name'), 'emails.sendDeviceLoginOtp', [
            'verificationCode' => $code,
            'nombre' => $usuario->nombre,
            'dispositivo' => $this->service_auth->nombreDispositivoDesdeUserAgent($request->userAgent()),
            'ip' => $request->ip(),
        ]);

        Log::info("Login desde equipo nuevo para usuario {$usuario->id_user}, verificación enviada");

        return response()->json([
            'error' => false,
            'message' => 'Verificación requerida',
            'data' => [
                'requires_otp' => true,
                'token' => $token,
                'correo' => $this->enmascararCorreo($usuario->correo),
            ],
        ]);
    }

    public function resendLoginOtp(Request $request)
    {
        $request->validate(
            ['token' => 'required|string|size:64'],
            ['token.required' => 'El token es obligatorio.', 'token.size' => 'El token no es válido.']
        );

        $pending = Cache::get("auth_login_pending_{$request->token}");

        if (! $pending) {
            return response()->json(['error' => true, 'message' => 'Sesión inválida o expirada. Inicia sesión nuevamente.'], 401);
        }

        $usuario = Usuario::on($pending['connection'])->find($pending['id']);

        if (! $usuario) {
            return response()->json(['error' => true, 'message' => 'Usuario no encontrado.'], 404);
        }

        $rateLimitKey = "auth_login_otp_send_{$usuario->id_user}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return response()->json(['error' => true, 'message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.'], 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("auth_login_otp_{$request->token}", ['code' => $code, 'id' => $usuario->id_user], now()->addMinutes(5));

        $this->mail_service->sendView($usuario->correo, 'Nuevo inicio de sesión — ' . config('app.name'), 'emails.sendDeviceLoginOtp', [
            'verificationCode' => $code,
            'nombre' => $usuario->nombre,
            'dispositivo' => $this->service_auth->nombreDispositivoDesdeUserAgent($request->userAgent()),
            'ip' => $request->ip(),
        ]);

        return response()->json(['error' => false, 'message' => 'Código reenviado']);
    }

    public function verifyLoginOtp(Request $request)
    {
        $request->validate(
            ['token' => 'required|string|size:64', 'code' => 'required|digits:5'],
            [
                'token.required' => 'El token es obligatorio.',
                'token.size' => 'El token no es válido.',
                'code.required' => 'El código de verificación es obligatorio.',
                'code.digits' => 'El código debe tener 5 dígitos.',
            ]
        );

        $token = $request->token;
        $key = "auth_login_otp_{$token}";
        $attemptsKey = "auth_login_otp_attempts_{$token}";

        $data = Cache::get($key);

        if (! $data) {
            return response()->json(['error' => true, 'message' => 'Token inválido o expirado.'], 400);
        }

        $attempts = Cache::increment($attemptsKey);

        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 5) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            Cache::forget("auth_login_pending_{$token}");

            return response()->json(['error' => true, 'message' => 'Demasiados intentos.'], 429);
        }

        if ($data['code'] !== $request->code) {
            return response()->json(['error' => true, 'message' => 'Código inválido.'], 400);
        }

        $pending = Cache::get("auth_login_pending_{$token}");

        if (! $pending) {
            return response()->json(['error' => true, 'message' => 'Sesión inválida o expirada. Inicia sesión nuevamente.'], 401);
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);
        Cache::forget("auth_login_pending_{$token}");

        $connection = $pending['connection'];
        config(['database.default' => $connection]);

        $usuario = Usuario::on($connection)->find($pending['id']);

        if (! $usuario) {
            return response()->json(['error' => true, 'message' => 'Usuario no encontrado.'], 404);
        }

        $deviceToken = $this->service_auth->registrarDispositivoConfiable($connection, $usuario->id_user, $request->ip(), $request->userAgent());

        // No hay contraseña en claro disponible en esta petición (solo viajó en el login
        // inicial) para repetir el inicio de sesión silencioso en SAMI — se omite acá, es
        // "best effort" también en el flujo normal (ver login()).
        return response()
            ->json(['Message' => 'Login Exitoso'])
            ->withCookie('token', $this->jwt->generateToken($usuario, $connection))
            ->withCookie($this->makeCookie($deviceToken, 'device_token', 60 * 24 * 90));
    }

    /**
     * Canje de una llave maestra (ver LlaveMaestraService) — pensado para un equipo de
     * terceros que no tiene sesión ni cookies previas, por eso es público (fuera del
     * grupo auth:api). A propósito NO pasa por nada de lo que hace login()/verifyLoginOtp()
     * para el equipo: no lee ni escribe la cookie `device_token`, no marca este equipo como
     * confiable, y no llama a SamiSsoService (no hay contraseña en claro disponible) — la
     * llave misma ya es el segundo factor, no debe además "recordar" el equipo desde el que
     * se usó.
     */
    public function redeemMasterKey(Request $request)
    {
        $request->validate(
            ['key' => 'required|string'],
            ['key.required' => 'La llave es obligatoria.']
        );

        $ip = $request->ip();
        $rateLimitKey = "master_key_redeem_{$ip}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(10));
        }

        if ($attempts > 10) {
            return response()->json(['error' => true, 'message' => 'Demasiados intentos. Intenta de nuevo más tarde.'], 429);
        }

        $resultado = $this->llaveMaestra->redimir($request->key, $ip);

        if (! $resultado) {
            return response()->json(['error' => true, 'message' => 'Llave inválida, ya usada o expirada.'], 401);
        }

        $connection = $resultado['connection'];
        config(['database.default' => $connection]);

        $usuario = Usuario::on($connection)->find($resultado['id_user']);

        if (! $usuario || $usuario->estado !== 'activo') {
            return response()->json(['error' => true, 'message' => 'El usuario destino ya no está activo.'], 404);
        }

        Log::warning('Sesión iniciada con llave maestra', [
            'id_user' => $usuario->id_user,
            'generado_por' => $resultado['generado_por'],
            'ip' => $ip,
        ]);

        return response()
            ->json(['Message' => 'Login Exitoso'])
            ->withCookie('token', $this->jwt->generateToken($usuario, $connection, [
                'via_llave_maestra' => true,
                'generado_por' => $resultado['generado_por'],
            ]));
    }

    private function enmascararCorreo(string $correo): string
    {
        [$local, $dominio] = explode('@', $correo, 2) + [1 => ''];

        $visible = mb_substr($local, 0, 1);

        return $visible . str_repeat('*', max(mb_strlen($local) - 1, 3)) . '@' . $dominio;
    }

    public function me()
    {
        return response()->json([
            'usuario' => auth('api')->user(),
        ]);
    }

    public function check(Request $request)
    {
        $system = $request->query('system');

        try {
            if ($system === 'admissions') {
                $token = $request->cookie('admissions_token');

                if (! $token) {
                    return response()->json(['active' => false], 401);
                }

                $user = JWTAuth::setToken($token)->authenticate();
                $user->load('perfilRelacion', 'nivelRelacion');

                return response()->json([
                    'active' => true,
                    'system' => 'admissions',
                    'usuario' => new UsuarioResource($user),
                ]);
            }

            if ($system === 'general') {
                $user = auth('api')->user();

                if (! $user) {
                    return response()->json(['active' => false], 401);
                }

                $user->load('perfilRelacion', 'nivelRelacion');

                return response()->json(array_merge([
                    'active' => true,
                    'system' => 'general',
                    'usuario' => new UsuarioResource($user),
                ], $this->masterKeyClaims($request)));
            }

            $admissionsToken = $request->cookie('admissions_token');

            if ($admissionsToken) {
                try {
                    $user = JWTAuth::setToken($admissionsToken)->authenticate();
                    $user->load('perfilRelacion', 'nivelRelacion');

                    return response()->json([
                        'active' => true,
                        'system' => 'admissions',
                        'usuario' => new UsuarioResource($user),
                    ]);

                } catch (\Exception $e) {
                    Log::error('admissions token inválido: '.$e->getMessage());
                }
            }

            $user = auth('api')->user();

            if (! $user) {
                return response()->json(['active' => false], 401);
            }

            $user->load('perfilRelacion', 'nivelRelacion');

            return response()->json(array_merge([
                'active' => true,
                'system' => 'general',
                'usuario' => new UsuarioResource($user),
            ], $this->masterKeyClaims($request)));

        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'message' => 'Token expirado o inválido: '.$e->getMessage(),
            ], 401);
        }
    }

    /**
     * `via_llave_maestra`/`generado_por` si el token `token` de esta petición fue emitido
     * por redeemMasterKey() — vacío en cualquier login normal. Usado por check() para que
     * el frontend pueda mostrar el banner de "sesión por llave maestra" sin decodificar el
     * JWT él mismo. Best-effort: un fallo leyendo el payload no debe tumbar /check.
     */
    private function masterKeyClaims(Request $request): array
    {
        $token = $request->cookie('token');

        if (! $token) {
            return [];
        }

        try {
            $payload = $this->jwt->getPayload($token);
        } catch (\Exception $e) {
            return [];
        }

        if (empty($payload['via_llave_maestra'])) {
            return [];
        }

        return [
            'via_llave_maestra' => true,
            'generado_por' => $payload['generado_por'] ?? null,
        ];
    }

    /**
     * Compartido entre ambos sistemas (system=general|admissions) — ver el registro de
     * la ruta en routes/api/auth.php. Invalidar el token y cerrar la sesión SAMI son
     * "best effort" (un token ya vencido/corrupto no debe impedir que la cookie se
     * limpie, que es el efecto que realmente le importa al usuario al hacer logout).
     */
    public function logout(Request $request)
    {
        $system = $request->query('system', 'general');
        $cookieName = $system === 'admissions' ? 'admissions_token' : 'token';
        $token = $request->cookie($cookieName);

        if ($token) {
            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (\Exception $e) {
                Log::warning('No se pudo invalidar el token en logout (puede ya estar vencido)', ['error' => $e->getMessage()]);
            }
        }

        try {
            $user = auth('api')->user();
            if ($user) {
                $this->samiSso->olvidarSesion($user->id_user);
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo cerrar la sesión SAMI SSO en logout', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Sesión cerrada correctamente',
        ])->withCookie(cookie()->forget($cookieName));
    }
}

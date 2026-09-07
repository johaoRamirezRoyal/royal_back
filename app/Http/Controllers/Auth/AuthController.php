<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Usuarios\UsuarioResource;
use App\Http\Traits\HasAuthCookie;
use App\Services\Auth\AuthServices;
use App\Services\branding\MarcaDominioService;
use App\Services\JwtService;
use App\Services\Sami\SamiSsoService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use HasAuthCookie;

    protected $service_usuarios;

    protected $service_auth;

    public function __construct(
        UsuariosServices $usuariosServices,
        AuthServices $service_auth,
        private JwtService $jwt,
        private SamiSsoService $samiSso,
        private MarcaDominioService $marcaDominioService,
    ) {
        $this->service_usuarios = $usuariosServices;
        $this->service_auth = $service_auth;
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

        // 🔹 Iniciar sesión silenciosa en SAMI (no bloqueante si falla)
        $this->samiSso->iniciarSesion($usuario->id_user, $usuario->user, $request->pass);

        return response()
            ->json(['Message' => 'Login Exitoso'])
            ->withCookie('token', $this->jwt->generateToken($usuario, $connection));
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

                return response()->json([
                    'active' => true,
                    'system' => 'general',
                    'usuario' => new UsuarioResource($user),
                ]);
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

            return response()->json([
                'active' => true,
                'system' => 'general',
                'usuario' => new UsuarioResource($user),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'message' => 'Token expirado o inválido: '.$e->getMessage(),
            ], 401);
        }
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

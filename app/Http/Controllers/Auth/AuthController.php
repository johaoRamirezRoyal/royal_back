<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasAuthCookie;
use App\Services\Auth\AuthServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use HasAuthCookie;

    protected $service_usuarios;

    protected $service_auth;

    public function __construct(UsuariosServices $usuariosServices, AuthServices $service_auth)
    {
        $this->service_usuarios = $usuariosServices;
        $this->service_auth = $service_auth;
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

            $token = JWTAuth::fromUser($userModel);
            $cookie = $this->makeCookie($token);

            return redirect("{$frontendUrl}/home")->withCookie($cookie);

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

        $credentials = [
            'user' => $request->user,
            'password' => $request->pass,
        ];

        if (! $token = auth()->guard('api')->attempt($credentials)) {
            return response()->json([
                'error' => true,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        // Usuario autenticado por JWT
        $usuario = auth('api')->user();

        // 🔹 Validación adicional de negocio (opcional)
        if ($usuario->estado !== 'activo') {
            return response()->json([
                'error' => true,
                'message' => 'Usuario inactivo',
            ], 403);
        }

        return $this->responseWithCookie($token);
    }

    private function responseWithCookie(string $token)
    {
        return response()
            ->json(['Message' => 'Login Exitoso'])
            ->withCookie($this->makeCookie($token));
    }

    public function me()
    {
        return response()->json([
            'usuario' => auth('api')->user(),
        ]);
    }

    public function check()
    {
        try {
            $user = auth('api')->user();

            if (! $user) {
                return response()->json([
                    'active' => false,
                ], 401);
            }

            return response()->json([
                'active' => true,
                'usuario' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'message' => 'Token expirado o inválido: '.$e->getMessage(),
            ], 401);
        }
    }

    public function logout()
    {
        try {
            auth()->guard('api')->logout();

            return response()->json([
                'error' => false,
                'message' => 'Sesión cerrada correctamente',
            ])->withCookie(cookie()->forget('token'));

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'No se pudo cerrar sesión',
            ], 500);
        }
    }
}

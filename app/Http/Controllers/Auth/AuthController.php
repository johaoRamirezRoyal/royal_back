<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mime\Message;

class AuthController extends Controller
{
    protected $service_usuarios;
    protected $service_auth;

    public function __construct(UsuariosServices $usuariosServices, AuthServices $service_auth)
    {
        $this->service_usuarios = $usuariosServices;
        $this->service_auth = $service_auth;
    }


    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "documento" => 'required|numeric|unique:usuarios,documento',
            "nombre" => 'required|string',
            "apellido" => 'nullable|string',
            "correo" => 'required|email|ends_with:@royalschool.edu.co|unique:usuarios,correo',
            'user' => 'required|string|unique:usuarios,user',
            'pass' => 'required|string|min:6',
            'perfil' => 'required|integer',
            'id_nivel' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()
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
            'estado' => 'activo'
        ];

        $response = $this->service_auth->registrarUsuario($data_usuario);

        if (!$response) {
            return response()->json([
                'error' => true,
                'message' => 'No se pudo registrar el usuario'
            ], 500);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario creado correctamente',
            'data' => $response
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
                'message' => $validator->errors()
            ], 422);
        }

        $credentials = [
            'user' => $request->user,
            'password' => $request->pass
        ];

        if (!$token = auth()->guard('api')->attempt($credentials)) {
            return response()->json([
                'error' => true,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Usuario autenticado por JWT
        $usuario = auth('api')->user();

        // 🔹 Validación adicional de negocio (opcional)
        if ($usuario->estado !== 'activo') {
            return response()->json([
                'error' => true,
                'message' => 'Usuario inactivo'
            ], 403);
        }

        return $this->responseWithCookie($token);

        return response()->json([
            'error' => false,
            'message' => 'Login exitoso',
            'token' => $token,
            'usuario' => $usuario
        ]);
    }

    private function responseWithCookie(string $token){
        $ttl = $ttl = 60 * 60; // 1 hora manual

        $cookie = cookie(
            name: 'token',
            value: $token,
            minutes: $ttl / 60,
            path: '/',
            domain: null,
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'Strict',
        );

        return response()
                ->json(['Message' => 'Login Exitoso'])
                ->withCookie($cookie);
    }

    public function me()
    {
        return response()->json([
            'usuario' => auth('api')->user()
        ]);
    }

    public function check(){
        try {
            $user = auth('api')->user();

            if(!$user){
                return response()->json([
                    'activo' => false
                ], 401);
            }

            return response()->json([
                'active' => true,
                'usuario' => $user
            ]);
        }catch(\Exception $e){
            return response()->json([
                'active' => false,
                'message' => 'Token expirado o invalido: ' . $e->getMessage()
            ], 401);
        }
    }

    public function logout()
    {
        try {
            auth()->guard('api')->logout();

            return response()->json([
                'error' => false,
                'message' => 'Sesión cerrada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'No se pudo cerrar sesión'
            ], 500);
        }
    }
}

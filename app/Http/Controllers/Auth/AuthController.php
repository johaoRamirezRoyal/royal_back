<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        if (!$token = auth('api')->attempt($credentials)) {
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

        return response()->json([
            'error' => false,
            'message' => 'Login exitoso',
            'token' => $token,
            'usuario' => $usuario
        ]);
    }

    public function me()
    {
        return response()->json([
            'usuario' => auth('api')->user()
        ]);
    }

    public function logout()
    {
        try {
            auth('api')->logout();

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

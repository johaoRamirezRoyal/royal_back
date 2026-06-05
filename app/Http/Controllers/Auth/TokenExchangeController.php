<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Usuarios\Usuario;

class TokenExchangeController extends Controller
{
    private array $rolesPermitidos = ['super admin', 'administrador', "admisiones"];
    private array $correosPermitidos = ['rector@colegio.edu'];

    public function exchange(Request $request)
    {
        // 1. Leer y validar el token ADMIN desde su cookie
        $adminToken = $request->cookie('token');

        if (!$adminToken) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        try {
            // Validar el token admin con tymon
            $adminPayload = JWTAuth::setToken($adminToken)->getPayload();
        } catch (\Exception $e) {
            Log::error('TokenExchange: token admin inválido', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Token admin inválido'], 401);
        }

        // 2. Verificar sistema correcto
        if ($adminPayload->get('system') !== 'general') {
            return response()->json(['error' => 'Token no corresponde al sistema admin'], 403);
        }

        // 3. Verificar rol o correo permitido
        $role  = $adminPayload->get('perfil');
        $email = $adminPayload->get('correo');

        $tienePermiso =
            in_array(strtolower($role), $this->rolesPermitidos) ||
            in_array($email, $this->correosPermitidos);

        if (!$tienePermiso) {
            Log::warning('TokenExchange: acceso denegado', [
                'email' => $email,
                'role'  => $role,
                'ip'    => $request->ip(),
            ]);
            return response()->json(['error' => 'No tienes permiso para acceder a admisiones'], 403);
        }

        // 4. Obtener el usuario y emitir token de ADMISIONES
        $userId = $adminPayload->get('user_id');
        $user   = Usuario::findOrFail($userId);

        // Claims extra que identifican este token como exchange
        $admisionesToken = JWTAuth::claims([
            'system' => 'admissions',
            'source' => 'token_exchange',
            'role'   => 'readonly_admin',
        ])->fromUser($user);

        Log::info('TokenExchange: acceso concedido', [
            'userId' => $userId,
            'email'  => $email,
            'ip'     => $request->ip(),
        ]);

        // 5. Setear la cookie admissions_token igual que lo hace tu sistema de admisiones
        return response()->json(['ok' => true])
            ->cookie(
                'admissions_token',
                $admisionesToken,
                120,            // 2 horas en minutos
                '/',
                null,
                true,           // secure (HTTPS)
                true,           // httpOnly
                false,
                'Strict'        // sameSite
            );
    }

    public function revoke(Request $request)
    {
        // Limpiar la cookie cuando el admin cierra la vista de admisiones
        return response()->json(['ok' => true])
            ->withoutCookie('admissions_token');
    }
}
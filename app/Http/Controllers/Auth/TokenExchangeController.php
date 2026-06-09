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

        // 4. Validar acudiente_id si se proporciona
        $acudienteId = $request->input('acudiente_id');

        if ($acudienteId !== null) {
            $request->validate([
                'acudiente_id' => 'integer|exists:usuarios,id_user',
            ]);
        }

        // 5. Obtener el usuario admin y emitir token de ADMISIONES
        $userId = $adminPayload->get('user_id');
        $user   = Usuario::findOrFail($userId);

        $admisionesToken = JWTAuth::claims([
            'system'       => 'admissions',
            'source'       => 'token_exchange',
            'role'         => 'admin',
            'acudiente_id' => $acudienteId,
        ])->fromUser($user);

        Log::info('TokenExchange: acceso concedido', [
            'adminId'     => $userId,
            'email'       => $email,
            'acudienteId' => $acudienteId,
            'ip'          => $request->ip(),
        ]);

        // 6. Cookie con la misma configuración que el sistema de admisiones
        return response()->json(['ok' => true])
            ->cookie(
                'admissions_token',
                $admisionesToken,
                120,
                '/',
                null,
                false,   // secure: igual que HasAuthCookie (false en dev)
                true,    // httpOnly
                false,
                'Lax'    // sameSite: igual que HasAuthCookie
            );
    }

    public function revoke(Request $request)
    {
        // Limpiar la cookie cuando el admin cierra la vista de admisiones
        return response()->json(['ok' => true])
            ->withoutCookie('admissions_token');
    }
}
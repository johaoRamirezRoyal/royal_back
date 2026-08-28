<?php

namespace App\Services\Auth;

use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Support\Facades\Hash;

class AuthServices
{
    /**
     * Login multi-tenant: el identificador puede ser `user` o `correo`, y el usuario puede
     * vivir en cualquiera de las bases con tabla `usuarios` (ver
     * BasesDatosService::connectionsConUsuarios). NO se acepta `documento` como
     * identificador — a diferencia de user/correo, el documento no es único a través de
     * tenants distintos (una misma persona con cuenta en dos colegios comparte documento),
     * así que buscar por documento sería ambiguo y silenciosamente entraría al tenant
     * equivocado. Se asume que `user`/`correo` sí son únicos a través de TODAS las bases —
     * apenas se encuentra una fila que matchea el identificador en una connection, esa es la
     * única candidata: si la contraseña o el estado fallan ahí, no se sigue buscando en
     * otras bases.
     *
     * @return array{usuario: Usuario, connection: string}|null
     */
    public function resolverUsuarioMultiTenant(string $identificador, string $password): ?array
    {
        foreach (BasesDatosService::connectionsConUsuarios() as $connection) {
            $usuario = Usuario::on($connection)
                ->where(function ($q) use ($identificador) {
                    $q->where('user', $identificador)
                        ->orWhere('correo', $identificador);
                })
                ->first();

            if (!$usuario) {
                continue;
            }

            if (!Hash::check($password, $usuario->pass) || $usuario->estado !== 'activo') {
                return null;
            }

            return ['usuario' => $usuario, 'connection' => $connection];
        }

        return null;
    }

    public function validarLoginUser($usuario) {
        return Usuario::where('user', $usuario)
            ->where('estado', 'activo')
            ->first();
    }

    public function registrarUsuario(Array $usuario){
        try {
            
            $usuario = Usuario::create($usuario);
            
            return [
                'success' => true,
                'data' => $usuario
            ];

        } catch (\Exception $e) {
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
        }
    }

    public function buscarUsuarioPorEmail($email){
        try{
            $usuario = Usuario::where('correo', $email)->first();
            if (!$usuario){
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $usuario
            ];
        }catch(\Exception $e){
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
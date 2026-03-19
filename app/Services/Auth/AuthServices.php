<?php

namespace App\Services\Auth; 

use App\Models\Usuario;

class AuthServices 
{
    public function validarLoginUser($usuario) {
        return Usuario::where('user', $usuario)
            ->where('estado', 'activo')
            ->first();
    }

    public function registrarUsuario(Usuario $usuario){
        try {
            $usuario = Usuario::create($usuario->toArray());
            
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
}
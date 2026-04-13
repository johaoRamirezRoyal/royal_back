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
<?php
namespace App\Services\Perfiles;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;

class PerfilesServices 
{
    public function mostrarTodosPerfiles(){
        try{
            $perfiles = Perfil::whereNot('id_perfil', 1)->get();

            return [
                'error' => false,
                'data' => $perfiles
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'data' => $e->getMessage(),
            ];
        }
    }

    /** Cambia el tipo (App\Enums\TipoPerfil) de un perfil. Super Admin no se toca. */
    public function actualizarTipo(int $idPerfil, TipoPerfil $tipo): array
    {
        $perfil = Perfil::find($idPerfil);

        if (! $perfil) {
            return ['error' => true, 'message' => 'El perfil no existe.', 'data' => null];
        }

        $perfil->update(['tipo' => $tipo->value]);

        return [
            'error' => false,
            'message' => "Tipo de \"{$perfil->nombre}\" actualizado.",
            'data' => ['id' => $perfil->id_perfil, 'tipo' => $perfil->tipo],
        ];
    }
}
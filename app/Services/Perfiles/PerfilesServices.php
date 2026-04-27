<?php
namespace App\Services\Perfiles;

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
}
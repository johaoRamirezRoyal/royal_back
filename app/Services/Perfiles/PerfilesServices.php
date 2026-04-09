<?php
namespace App\Services\Perfiles;

use App\Models\Perfil;

class PerfilesServices 
{
    public function mostrarTodosPerfiles(){
        try{
            $perfiles = Perfil::all();

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
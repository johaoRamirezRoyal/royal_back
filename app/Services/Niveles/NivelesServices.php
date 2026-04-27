<?php

namespace App\Services\Niveles;

use App\Models\Usuarios\Nivel;

class NivelesServices 
{
    public function mostrarTodosNiveles(){
        try{
            $niveles = Nivel::all();

            return [
                'error' => false,
                'data' => $niveles
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
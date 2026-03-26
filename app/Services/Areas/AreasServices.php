<?php
namespace App\Services\Areas;

use App\Models\Areas;

class AreasServices 
{
    public function crearArea($datos){
        $nombre = $datos["nombre"];
        $user_log = $datos["user_log"];

        try{
            $area_nueva = Areas::create([
                "nombre"=> $nombre,
                "user_log" => $user_log,
                "fechareg" => now()
            ])->latest("id")->first();

            return [
                "error" => false,
                "data" => $area_nueva->toArray()
            ];
        }catch(\Exception $e){
            return [
                "error"=> true,
                "message" => $e->getMessage()
            ];
        }
    }

    public function obtenerTodasLasAreas(){
        try {
            $areas = Areas::get();
            return [
                'error' => false,
                'data' => $areas
            ];
        }catch(\Exception $e){
            return [
                'error'=> true,
                'message'=> $e->getMessage()
            ];
        }
    }
}
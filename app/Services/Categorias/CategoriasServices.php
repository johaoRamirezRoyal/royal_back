<?php 

namespace App\Services\Categorias;

use App\Models\Categoria;
use Illuminate\Support\Facades\DB;

class CategoriasServices
{
    public function obtenerTodasLasCategorias(){
        try{
            $categorias = Categoria::where("activo", 1)->get();

            return [
                "error" => false,
                "data" => $categorias,
                "message" => "Categorias obtenidas correctamente"
            ];

        }catch(\Exception $e){
            return [
                "error" => true,
                "data" => null,
                "message" => $e->getMessage(),
            ];
        }
    }

    public function agregarNuevaCategoria(array $datos){
        try{
            $categoria = Categoria::create($datos);

            return [
                "error" => false,
                "data" => $categoria,
                "message" => "Categoria Creada con éxito",
            ];
        }catch(\Exception $e){
            return [
                "error" => true,
                "data" => null,
                "message" => $e->getMessage(),
            ];
        }
    }

    public function actualizarCategoria(array $datos, array $ids){
        try{
            $actualizados = Categoria::whereIn('id', $ids)->update($datos, $ids);

            if($actualizados < 1){
                return [
                    "error" => true,
                    "data" => $actualizados,
                    "message" => "No se actualizó ninguna categoria"
                ];
            }

            return [
                "error" => false,
                "data" => $actualizados,
                "message" => "Categoria actualizada",
            ];
        }catch(\Exception $e){
            return [
                "error" => true,
                "message" => $e->getMessage(),
                "data" => null,
            ];
        }
    }
}
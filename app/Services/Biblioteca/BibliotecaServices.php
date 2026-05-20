<?php

namespace App\Services\Biblioteca;

use App\Models\Biblioteca\Categoria;
use App\Models\Biblioteca\Subcategoria;
use App\Services\Service;

class BibliotecaServices extends Service
{
    /*
    -------------------------------------------------
    |
    |             CATEGORIAS 
    |
    -------------------------------------------------
    */

    /**
     * Función para obtener todas las categorias de biblioteca
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarCategoriasBiblioteca(): array
    {
        try {
            $categorias = Categoria::activo()->get();

            if ($categorias->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No hay categorias para mostrar",
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => "Obtenidas las categorias",
                'data' => $categorias->toArray()
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al obtener las categorias");
            return [
                'error' => true,
                'message' => "Error en el servidor para obtener las categorias",
                'data' => [],
            ];
        }
    }

    /**
     * Agregar categoria de biblioteca
     * @param array $data
     * @return array{data: array, error: bool, message: string}
     */
    public function agregarCategoriaBiblioteca(array $data): array {
        try {

            if(empty($data)){
                return [
                    'error' => true,
                    'message' => "No llegaron datos al servidor",
                    'data' => [],
                ];
            }

            $nueva_categoria = Categoria::create($data);
            if(!$nueva_categoria){
                return [
                    "error" => true,
                    "message" => "No se pudo crear la nueva categoria",
                    "data" => [],
                ];
            }

            return [
                "error" => false,
                'message' => "Categoria creada",
                "data" => $nueva_categoria->toArray(),
            ];
        }catch(\Exception $e){
            $this->sendError($e, "Error en el servidor al tratar de crear una categoria");
            return [
                'error' => true,
                "message" => "No se pudo agregar la categoria, error en el servicio",
                "data" => []
            ];
        }
    }

    /**
     * Mostrar subcategoria de biblioteca
     * @param mixed $id_categoria
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarSubcategoriasBiblioteca(?int $id_categoria = null): array
    {
        try {
            $subcategorias = Subcategoria::activo()
                ->categoria($id_categoria)
                ->with('categoria')
                ->get();
                
            if ($subcategorias->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontraron subcategorias",
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => "Subcategorias encontradas",
                'data' => $subcategorias->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al obtener las subcategorias");
            return [
                'error' => true,
                'message' => "No se pudieron obtener las categorias, error de servidor",
                'data' => [],
            ];
        }
    }

    /**
     * Agregar subcategoria biblioteca
     * @param array $data
     * @return array{data: array, error: bool, message: string}
     */
    public function agregarSubcategoriaBiblioteca(array $data){
        try {
            if(empty($data)){
                return [
                    "error" => true,
                    "message" => "No llegaron los datos al servicio",
                    "data" => [],
                ];
            }

            $subcategoriaNueva = Subcategoria::create($data);

            if(!$subcategoriaNueva){
                return [
                    "error" => true,
                    "message" => "No se ha podido crear la nueva subcategoria",
                    "data" => $data
                ];
            }

            return [
                "error" => false,
                "message" => "Subcategoria creada con éxito",
                'data' => $subcategoriaNueva->toArray(),
            ];
        }catch(\Exception $e){
            $this->sendError($e, "Error al crear la subcategoria: ");
            return [
                "error" => true,
                "message" => "Error en el servidor al crear la subcategoria",
                "data" => [],
            ];
        }
    }
}

<?php

namespace App\Services\Biblioteca;

use App\Models\Biblioteca\Categoria;
use App\Models\Biblioteca\Libro;
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
    public function agregarCategoriaBiblioteca(array $data): array
    {
        try {

            if (empty($data)) {
                return [
                    'error' => true,
                    'message' => "No llegaron datos al servidor",
                    'data' => [],
                ];
            }

            $nueva_categoria = Categoria::create($data);
            if (!$nueva_categoria) {
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
        } catch (\Exception $e) {
            $this->sendError($e, "Error en el servidor al tratar de crear una categoria");
            return [
                'error' => true,
                "message" => "No se pudo agregar la categoria, error en el servicio",
                "data" => []
            ];
        }
    }

    /**
     * Metodo para actualizar el estado de una categoria
     * @param int $id_categoria
     * @param int $estado
     * @return array{data: array, error: bool, message: string}
     */
    public function cambiarEstadoCategoriaBiblioteca(int $id_categoria, int $estado)
    {
        try {
            $categoria = Categoria::find($id_categoria);

            if (!$categoria) {
                return [
                    "error" => true,
                    "message" => "No se ha encontrado la categoria con ID: " . $id_categoria,
                    "data" => []
                ];
            }

            if (!in_array($estado, [0, 1])) {
                return [
                    "error" => true,
                    "message" => "Solo hay 2 estados, activos e inactivos: 0 inactivos, 1 activo",
                    "data" => $categoria->toArray()
                ];
            }

            $categoriaUpdate = $categoria->update(["activo" => $estado]);

            if (!$categoriaUpdate) {
                return [
                    "error" => true,
                    "message" => "Error al tratar de actualizar la categoria",
                    "data" => $categoria->toArray(),
                ];
            }

            return [
                "error" => false,
                "message" => "Se cambió el estado de la categoria correctamente",
                "data" => $categoria->refresh()->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al cambiar el estado de la categoria.");
            return [
                "error" => true,
                "message" => "Error en el servidor al cambiar el estado de la categoria.",
                "data" => [],
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
    public function agregarSubcategoriaBiblioteca(array $data)
    {
        try {
            if (empty($data)) {
                return [
                    "error" => true,
                    "message" => "No llegaron los datos al servicio",
                    "data" => [],
                ];
            }

            $subcategoriaNueva = Subcategoria::create($data);

            if (!$subcategoriaNueva) {
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
        } catch (\Exception $e) {
            $this->sendError($e, "Error al crear la subcategoria: ");
            return [
                "error" => true,
                "message" => "Error en el servidor al crear la subcategoria",
                "data" => [],
            ];
        }
    }

    public function cambiarEstadoSubcategoriaBiblioteca(int $id_subcategoria, int $estado)
    {
        try {
            $subcategoria = Subcategoria::find($id_subcategoria);

            if (!$subcategoria) {
                return [
                    "error" => true,
                    "message" => "No se ha encontrado la categoria con ID: " . $id_subcategoria,
                    "data" => []
                ];
            }

            if (!in_array($estado, [0, 1])) {
                return [
                    "error" => true,
                    "message" => "Solo hay 2 estados, activos e inactivos: 0 inactivos, 1 activo",
                    "data" => $subcategoria->toArray()
                ];
            }

            $categoriaUpdate = $subcategoria->update(["activo" => $estado]);

            if (!$categoriaUpdate) {
                return [
                    "error" => true,
                    "message" => "Error al tratar de actualizar la categoria",
                    "data" => $subcategoria->toArray(),
                ];
            }

            return [
                "error" => false,
                "message" => "Se cambió el estado de la categoria correctamente",
                "data" => $subcategoria->refresh()->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al cambiar el estado de la categoria.");
            return [
                "error" => true,
                "message" => "Error en el servidor al cambiar el estado de la categoria.",
                "data" => [],
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |                   LIBROS 
    |
    -------------------------------------------------
    */

    /**
     * Método para obtener todos los libros de forma filtrada y páginada
     * @param mixed $categoria
     * @param mixed $subcategoria
     * @param mixed $estado
     * @param int $perpage
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerTodosLosLibrosBiblioteca(?string $search = null, ?array $categoria = null, ?array $subcategoria = null, ?int $estado = 1, int $perpage): array
    {
        try {

            $libros = Libro::query()
                ->where('activo', $estado)

                ->with([
                    'categoria:id,nombre',
                    'subcategoria:id,nombre'
                ])

                ->when(
                    !empty($categoria),
                    fn($q) => $q->whereIn(
                        'id_categoria',
                        $categoria
                    )
                )

                ->when(
                    !empty($subcategoria),
                    fn($q) => $q->whereIn(
                        'id_subcategoria',
                        $subcategoria
                    )
                )

                ->when(
                    !empty($search), 
                    fn($q) => $q
                                ->where('titulo', 'LIKE', "%$search%")
                                ->orWhere('autor', 'LIKE', "%$search%")
                                ->orWhere('editorial', 'LIKE', "%$search%")
                )

                ->paginate($perpage);

            if ($libros->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron libros',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Libros listados',
                'data' => $libros->toArray()
            ];
        } catch (\Exception $e) {

            $this->sendError(
                $e,
                'Error al listar libros'
            );

            return [
                'error' => true,
                'message' => 'Error del servidor',
                'data' => []
            ];
        }
    }

    /**
     * Método para añadir un nuevo libro
     * @param array $data
     * @return array{data: array, error: bool, message: string}
     */
    public function agregarNuevoLibroBiblioteca(array $data)
    {
        try {
            if (empty($data)) {
                return [
                    "error" => true,
                    "message" => "No ha llegado datos al servidor",
                    "data" => [],
                ];
            }

            $libro = Libro::create($data);

            if (!$libro) {
                return [
                    'error' => true,
                    "message" => "No se pudo crear el libro",
                    "data" => $data,
                ];
            }

            return [
                "error" => false,
                "message" => "Libro creado exitosamente",
                "data" => $libro->load([
                    'categoria:id,nombre',
                    'subcategoria:id,nombre'
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al crear el libro");
            return [
                "error" => true,
                "message" => "Error en el servidor al tratar de crear el libro",
                "data" => []
            ];
        }
    }
}

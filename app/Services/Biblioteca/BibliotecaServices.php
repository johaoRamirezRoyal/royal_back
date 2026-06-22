<?php

namespace App\Services\Biblioteca;

use App\Models\Biblioteca\Categoria;
use App\Models\Biblioteca\Ejemplares;
use App\Models\Biblioteca\Libro;
use App\Models\Biblioteca\PaqueteContenido;
use App\Models\Biblioteca\Paquetes;
use App\Models\Biblioteca\PrestamosEjemplar;
use App\Models\Biblioteca\Subcategoria;
use App\Services\FileStorageService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

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
        } catch (Exception $e) {

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


    public function editarLibro(array $data, int $id_libro){
        try {
            $libro = Libro::find($id_libro);

            if(!$libro){
                return [
                    'error' => true,
                    'message' => "No se ha encontrado el libro con el ID: ". $id_libro,
                    'data' => []
                ];
            }

            if (isset($data['foto'])) {

                if ($libro->foto) {
                    $rutaAbsoluta = storage_path('app/public/' . $libro->foto);
                    File::delete($rutaAbsoluta);
                }

                $fileStorageService = app(FileStorageService::class);

                $nuevaFoto = $fileStorageService->uploadFile(
                    $data['foto'],
                    'biblioteca'
                );

                if (!$nuevaFoto || !isset($nuevaFoto['ruta'])) {
                    return [
                        'error' => true,
                        'message' => 'No fue posible actualizar la imagen',
                        'data' => []
                    ];
                }

                $data['foto'] = $nuevaFoto['ruta'];
            }

            $libroUpdate = $libro->update($data);

            if(!$libroUpdate){
                return [
                    'error' => true,
                    'message' => "Error al actualizar el libro",
                    'data' => $libro->toArray()
                ];
            }

            return [
                'error' => false,
                'message' => "Libro actualizado correctamente",
                'data' => $libro->refresh()->toArray()
            ];
        }catch(Exception $e){
            $this->sendError($e, "Error al actualizar la información del libro");
            return [
                'error' => true,
                'message' => "Error en el servidor al actualizar la información del libro",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoLibro(array $id_libros, ?int $estado = 0){
        try {
            $libros = Libro::whereIn('id', $id_libros);

            if(!$libros->exists()){
                return [
                    'error' => true,
                    'message' => "No se encontró el libro para hacer el cambio de estado",
                    'data' => []
                ];
            }

            $libroUpdate = $libros->update(
                [
                    'activo' => $estado
                ]
            );

            if (!$libroUpdate) {
                return [
                    'error' => true,
                    'message' => "Error al cambiar el estado del libro",
                    'data' => $libros->toArray()
                ];
            }

            $librosActualizados = Libro::whereIn('id', $id_libros)->get();

            return [
                'error' => false,
                'message' => "Libro actualizado correctamente",
                'data' => $librosActualizados->toArray()
            ];

        }catch(Exception $e){
            $this->sendError($e, "Error al actualizar la información del libro");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de actualizar el estado del libro",
                'data' => []
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |                   EJEMPLARES 
    |
    -------------------------------------------------
    */
    public function agregarEjemplarLibroBiblioteca(array $data, int $cantidad): array
    {
        try {
            DB::beginTransaction();

            $ultimo = Ejemplares::max('id') ?? 0;

            $ejemplares = [];

            for ($i = 1; $i <= $cantidad; $i++) {
                $ultimo++;
                $ejemplares[] = [
                    "id_libro" => $data['id_libro'],
                    "codigo" => 'EJEM-' . str_pad($ultimo, 4, '0', STR_PAD_LEFT),
                    "estado" => $data['estado'] ?? 1,
                    "id_log" => $data['id_log'],
                ];
            }

            Ejemplares::insert($ejemplares);
            DB::commit();

            return [
                "error" => false,
                "message" => "{$cantidad} ejemplares creados correctamente",
                "data" => []
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->sendError($e, "Error al tratar de crear un ejemplar");
            return [
                "error" => true,
                "message" => "Error en el servidor al crear el ejemplar",
                "data" => []
            ];
        }
    }

    /**
     * Metodo para ver los ejemplares de un libro, puede ser filtrado
     * @param mixed $id_libro
     * @param mixed $autor
     * @param mixed $perpage
     * @return array{data: array, error: bool, message: string}
     */
    public function verEjemplaresLibroBiblioteca(?int $id_libro = null, ?string $autor = null, ?int $perpage = 10)
    {
        try {
            $ejemplares = Ejemplares::query()
                ->activo()
                ->with([
                    'libro:id,titulo,autor,editorial,foto',
                    'prestamos:id,id_ejemplar,id_usuario,fecha_prestamo,fecha_devolucion,observacion,id_devuelto,fecha_devuelto',
                    'prestamos.usuario:id_user,nombre,apellido,correo'
                ])
                ->when(
                    !empty($id_libro),
                    fn($q) => $q->where('id_libro', $id_libro)
                )
                ->when(
                    !empty($autor),
                    fn($q) => $q->whereHas(
                        'libro',
                        fn($sub) => $sub->where(
                            'autor',
                            'LIKE',
                            "%$autor%"
                        )
                    )
                )
                ->paginate($perpage);

            if ($ejemplares->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontraron ejemplares",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Ejemplares listados correctamente",
                'data' => $ejemplares->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error en el servidor al tratar de listar los ejemplares");
            return [
                'error' => true,
                "message" => "Error en el servidor al listar ejemplares",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoEjemplarBiblioteca(?array $ids_ejemplares, ?int $estado = 4): array
    {
        if (empty($ids_ejemplares)) {
            return [
                'error' => true,
                'message' => "No han llegado los ids de los ejemplares a cambiar estado",
                'data' => []
            ];
        }

        try {
            $estado = $estado ?? 4;

            $cantidadActualizados = Ejemplares::whereIn('id', $ids_ejemplares)
                ->update([
                    'estado'         => $estado,
                    'fecha_inactivo' => $estado == 1 ? null : now()->toDateTimeString()
                ]);

            return [
                'error' => false,
                'message' => "Se han actualizado los {$cantidadActualizados} ejemplar(es)",
                'data' => []
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error en el servidor al actualizar el estado de los ejemplares");
            return [
                'error' => true,
                'message' => "Ha ocurrido un error en el servidor al momento de actualizar el estado de los ejemplares",
                'data' => []
            ];
        }
    }


    /*
    -------------------------------------------------
    |
    |             BIBLIOTECA PRESTAMOS 
    |
    -------------------------------------------------
    */

    /**
     * Método para ver los prestamos de un ejemplar
     * @param int $id_ejemplar
     * @return array{data: array, error: bool, message: string}
     */
    public function verPrestamosDeEjemplar(int $id_ejemplar): array
    {
        try {

            $prestamos = PrestamosEjemplar::query()
                ->where("id_ejemplar", $id_ejemplar)

                ->with("ejemplar:id,id_libro,codigo,estado")

                ->with(
                    "ejemplar.libro:id,
                titulo,
                autor,
                editorial,
                edicion,
                foto,
                id_categoria,
                id_subcategoria"
                )

                ->with("ejemplar.libro.categoria:id,nombre")

                ->with("ejemplar.libro.subcategoria:id,nombre")

                ->with("usuario:id_user,nombre,apellido,correo")

                ->orderByDesc("fecha_prestamo")

                ->get();

            if ($prestamos->isEmpty()) {
                return [
                    'error' => false,
                    'message' => "No se encontraron prestamos para el ejemplar",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Prestamos obtenidos",
                'data' => $prestamos
            ];
        } catch (\Exception $e) {

            $this->sendError(
                $e,
                "Error en el servidor al tratar de listar los prestamos del ejemplar"
            );

            return [
                'error' => true,
                'message' => "Ha ocurrido un error inesperado al listar los prestamos del ejemplar",
                'data' => []
            ];
        }
    }

    /**
     * Ver prestamos de un libro y los respectivos ejemplares
     * @param int $id_libro
     * @return array{data: array, error: bool, message: string}
     */
    public function verPrestamosLibro(int $id_libro): array
    {
        if (!$id_libro) {
            return [
                'error' => true,
                'message' => 'No se envió el ID del libro',
                'data' => []
            ];
        }

        try {

            $libro = Libro::query()

                ->where('id', $id_libro)

                ->with([

                    'categoria:id,nombre',

                    'subcategoria:id,nombre',

                    'ejemplares:id,id_libro,codigo,estado',

                    'ejemplares.prestamos:id,id_ejemplar,id_usuario,fecha_prestamo,fecha_devolucion,observacion,id_devuelto,fecha_devuelto',

                    'ejemplares.prestamos.usuario:id_user,nombre,apellido,correo',

                    'ejemplares.prestamos.ejemplar:id,id_libro,codigo,estado'

                ])

                ->first();

            if (!$libro) {
                return [
                    'error' => true,
                    'message' => 'Libro no encontrado',
                    'data' => []
                ];
            }

            $prestamos = $libro
                ->ejemplares
                ->flatMap(fn($e) => $e->prestamos)
                ->values();

            return [
                'error' => false,
                'message' => 'Préstamos encontrados',
                'data' => [

                    'libro' => [
                        'id' => $libro->id,
                        'titulo' => $libro->titulo,
                        'autor' => $libro->autor,
                        'editorial' => $libro->editorial,
                        'edicion' => $libro->edicion,
                        'foto' => $libro->foto,
                        'categoria' => $libro->categoria,
                        'subcategoria' => $libro->subcategoria,
                    ],

                    'prestamos' => $prestamos
                ]
            ];
        } catch (\Exception $e) {

            $this->sendError(
                $e,
                "Error al listar préstamos"
            );

            return [
                'error' => true,
                'message' => 'Error inesperado',
                'data' => []
            ];
        }
    }

    /**
     * Prestar un ejemplar de un libro usando el codigo de dicho ejemplar.
     * @param array $datos
     * @return array{data: array, error: bool, message: string}
     */
    public function prestarEjemplarBiblioteca(array $datos): array
    {
        try {


            $datos["fecha_prestamo"] = now();

            $ejemplar = Ejemplares::where('codigo', $datos['codigo_ejemplar'])->first();

            if (!$ejemplar) {
                return [
                    'error' => true,
                    'message' => "Ese ejemplar no existe en la Base de Datos",
                    'data' => $datos,
                ];
            }

            if ($ejemplar->estado != 1) {
                return [
                    'error' => true,
                    'message' => "El ejemplar seleccionado no se encuentra disponible para realizar prestamos",
                    'data' => $datos
                ];
            }


            $prestamo_activo = PrestamosEjemplar::where('id_ejemplar', $ejemplar->id)
                ->whereNull('fecha_devuelto')->exists();

            if ($prestamo_activo) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => "Este ejemplar ya se encuentra prestado y no lo han devuelto...",
                    'data' => $datos
                ];
            }

            DB::beginTransaction();

            $datos['id_ejemplar'] = $ejemplar->id;
            unset($datos['codigo_ejemplar']);

            $prestamo = PrestamosEjemplar::create($datos);
            $ejemplar->update(['estado' => 2]);

            if (empty($prestamo)) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => "El prestamo no pudo ser realizado",
                    'data' => $datos
                ];
            }

            DB::commit();

            return [
                'error' => false,
                'message' => "Prestamo realizado correctamente",
                'data' => $prestamo->toArray()
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->sendError($e, "Error en realizar prestamo de ejemplar:");

            return [
                'error' => true,
                'message' => "Ha ocurrido un error inesperado al realizar el prestamo...",
                'data' => $datos
            ];
        }
    }

    public function devolverPrestamoEjemplarBiblioteca(array $data): array
    {
        if (!$data['codigo_ejemplar']) {
            return [
                'error' => true,
                'message' => "Es necesario el código del ejemplar para poder realizar la devolución",
                'data' => $data
            ];
        }
        try {
            DB::beginTransaction();

            $ejemplar = Ejemplares::where('codigo', $data['codigo_ejemplar'])->first();

            if (!$ejemplar) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => "No se encontró el ejemplar con ese código",
                    'data' => $data
                ];
            }

            $prestamo = PrestamosEjemplar::where('id_ejemplar', $ejemplar->id)
                ->whereNull('fecha_devuelto')
                ->first();

            if (!$prestamo) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => "Este ejemplar no se encuentra en prestamo",
                    'data' => $data
                ];
            }

            $ejemplar->update(['estado' => 1]);
            $prestamo->update(['id_devuelto' => $data['id_log'], 'fecha_devuelto' => now()]);

            DB::commit();

            return [
                'error' => false,
                'message' => "Ejemplar devuelto exitosamente",
                'data' => $data
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            $this->sendError($e, "Error en el servidor al tratar de devolver el prestamo:");
            return [
                'error' => true,
                'message' => "Error inesperado al devolver el prestamo del ejemplar, comuniquese con el area de sistemas si el problema continua",
                'data' => $data
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             BIBLIOTECA PAQUETES 
    |
    -------------------------------------------------
    */

    /**
     * Metodo para listar los paquetes y su respectivo contenido
     * @param mixed $search
     * @param mixed $perpage
     * @return array{data: array, error: bool, message: string}
     */
    public function listarPaquetesBiblioteca(?string $search = null, ?int $perpage = 10): array
    {
        try {
            $paquetes = Paquetes::with(
                ['contenidos']
            )
                ->when(!empty($search), fn($q) => $q->where("nombre", 'LIKE', "%$search%"))
                ->paginate($perpage);

            if ($paquetes->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No hay paquetes a listar",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Paquetes listados completamente",
                'data' => $paquetes,
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error en el servidor al tratar de listar los paquetes");
            return [
                'error' => true,
                'message' => "Ha ocurrido un error inesperado al tratar de listar los paquetes.",
                'data' => []
            ];
        }
    }

    /**
     * Metodo para crear un nuevo paquete
     * @param array $data
     * @return array{data: array, error: bool, message: string}
     */
    public function crearNuevoPaqueteBiblioteca(array $data): array
    {
        try {
            $ultimo_paquete = (Paquetes::max('id') ?? 0) + 1;
            $data['codigo'] = 'PACK-' . str_pad($ultimo_paquete, 4, '0', STR_PAD_LEFT);

            $paquete_nuevo = Paquetes::create($data);

            if (!$paquete_nuevo) {
                return [
                    'error' => true,
                    'message' => "No se creo el paquete nuevo",
                    'data' => $data
                ];
            }

            return [
                'error' => false,
                'message' => "Paquete creado éxitosamente!",
                'data' => $paquete_nuevo->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al crear el nuevo paquete");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de crear un nuevo paquete",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoPaqueteBiblioteca(array $ids_paquetes, int $estado){
        try {
            $paquetes = Paquetes::whereIn('id', $ids_paquetes);

            if (!in_array($estado, [0, 1])) {
                return [
                    'error' => true,
                    'message' => 'Estado inválido',
                    'data' => []
                ];
            }

            if(!$paquetes->exists()){
                return [
                    'error' => true,
                    'message' => "No se encontró el paquete para hacer el cambio de estado",
                    'data' => []
                ];
            }

            $filasAfectadas = $paquetes->update([
                'activo' => $estado
            ]);

            if ($filasAfectadas === 0) {
                return [
                    'error' => true,
                    'message' => 'No se actualizó ningún paquete',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se cambió el estado del paquete correctamente",
                'data' => []
            ];
            
        }catch(Exception $e){
            $this->sendError($e, "Error al actualizar el estado del paquete");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de actualizar el estado de los paquetes",
                'data' => []
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |         BIBLIOTECA CONTENIDO PAQUETES 
    |
    -------------------------------------------------
    */

    public function agregarContenidoPaqueteBiblioteca(array $data){
        try {

            $ultimoCodigo = PaqueteContenido::max('codigo');

            $ultimoNumero = $ultimoCodigo
                ? (int) str_replace('CONT-', '', $ultimoCodigo)
                : 0;

            $data['codigo'] = 'CONT-' . str_pad($ultimoNumero + 1, 4, '0', STR_PAD_LEFT);

            $contenido = PaqueteContenido::create($data);

            return [
                'error' => false,
                'message' => 'Contenido creado correctamente',
                'data' => $contenido->fresh()->toArray()
            ];

        }catch(Exception $e){

            $this->sendError($e, "Error al crear el contenido");

            return [
                'error' => true,
                'message' => "Error en el servidor al crear el contenido",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoContenidoPaqueteBiblioteca(array $ids, int $id_paquete, int $estado){
        try {
            $contenidos = PaqueteContenido::whereIn('id', $ids)->where('id_paquete', $id_paquete);

            if(!$contenidos->exists()){
                return [
                    'error' => true,
                    'message' => "No se encontró el contenido para hacer el cambio de estado",
                    'data' => []
                ];
            }

            $updateData = [
                'activo' => $estado
            ];

            if ($estado === 0) {
                $updateData['fecha_inactivo'] = now();
                $idUsuario = Auth::id();
                $updateData['id_inactivo'] = $idUsuario;
            }

            $contenidosUpdate = $contenidos->update($updateData);

            if ($contenidosUpdate === 0) {
                return [
                    'error' => true,
                    'message' => "No se actualizó ningún contenido",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se cambió el estado del contenido correctamente",
                'data' => ["actualizado" => $contenidosUpdate],
            ];
        }catch(Exception $e){
            $this->sendError($e, "Error al actualizar el estado del contenido");
            return [
                'error' => true,
                'message' => "Error en el servidor al actualizar el estado del contenido",
                'data' => []
            ];
        }
    }
}

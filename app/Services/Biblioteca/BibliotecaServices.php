<?php

namespace App\Services\Biblioteca;

use App\Models\Biblioteca\Categoria;
use App\Models\Biblioteca\Ejemplares;
use App\Models\Biblioteca\Libro;
use App\Models\Biblioteca\PaqueteContenido;
use App\Models\Biblioteca\PaquetePrestamos;
use App\Models\Biblioteca\Paquetes;
use App\Models\Biblioteca\PrestamosEjemplar;
use App\Models\Biblioteca\Subcategoria;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Mail\RecordatorioPrestamosEmail;
use App\Services\FileStorageService;
use App\Services\MailService;
use App\Pdf\Biblioteca\PazYSalvoPdfService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Arr;
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

                ->withCount(['ejemplares as cantidad_ejemplares' => fn($q) => $q->activo()])

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


    public function editarLibro(array $data, int $id_libro)
    {
        try {
            $libro = Libro::find($id_libro);

            if (!$libro) {
                return [
                    'error' => true,
                    'message' => "No se ha encontrado el libro con el ID: " . $id_libro,
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

            if (!$libroUpdate) {
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
        } catch (Exception $e) {
            $this->sendError($e, "Error al actualizar la información del libro");
            return [
                'error' => true,
                'message' => "Error en el servidor al actualizar la información del libro",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoLibro(array $id_libros, ?int $estado = 0)
    {
        try {
            $libros = Libro::whereIn('id', $id_libros);

            if (!$libros->exists()) {
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
        } catch (Exception $e) {
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
    |             ESTADISTICAS
    |
    -------------------------------------------------
    */

    public function estadisticasEjemplaresBiblioteca(?int $id_libro = null): array
    {
        try {
            $counts = Ejemplares::selectRaw('estado, count(*) as total')
                ->whereIn('estado', [1, 2])
                ->when(
                    !empty($id_libro),
                    fn($q) => $q->where('id_libro', $id_libro)
                )
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $data = [
                'disponibles' => (int) ($counts[1] ?? 0),
                'prestados'   => (int) ($counts[2] ?? 0),
            ];

            if (empty($id_libro)) {
                $data['ejemplares_total'] = Ejemplares::count();
                $data['libros_total'] = Libro::count();
                $data['libros_inactivos'] = Libro::where('activo', 0)->count();
            }

            return [
                'error'   => false,
                'message' => 'Estadísticas obtenidas',
                'data'    => $data
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener estadísticas de ejemplares');
            return [
                'error'   => true,
                'message' => 'Error en el servidor al obtener estadísticas',
                'data'    => []
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
     * Verifica si un ejemplar existe (por su código) y si está disponible para préstamo.
     * @return array{data: array, error: bool, message: string}
     */
    public function verificarEjemplarBiblioteca(string $codigo): array
    {
        try {
            $ejemplar = Ejemplares::where('codigo', $codigo)
                ->with('libro:id,titulo,autor,editorial,foto')
                ->first();

            if (!$ejemplar) {
                return [
                    'error' => true,
                    'message' => 'El ejemplar no existe',
                    'data' => ['existe' => false],
                ];
            }

            return [
                'error' => false,
                'message' => $ejemplar->estado == 1
                    ? 'El ejemplar está disponible para préstamo'
                    : 'El ejemplar existe pero no está disponible para préstamo',
                'data' => [
                    'existe' => true,
                    'disponible' => $ejemplar->estado == 1,
                    'estado' => $ejemplar->estado,
                    'ejemplar' => $ejemplar,
                ],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al tratar de verificar el ejemplar");
            return [
                'error' => true,
                'message' => "Error en el servidor al verificar el ejemplar",
                'data' => [],
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
    public function verEjemplaresLibroBiblioteca(?int $id_libro = null, ?string $autor = null, ?string $search = null, ?int $perpage = 10)
    {
        try {
            $ejemplares = Ejemplares::query()
                ->activo()
                ->with([
                    'libro:id,titulo,autor,editorial,foto',
                    'prestamos:id,id_ejemplar,id_usuario,fecha_prestamo,fecha_devolucion,observacion,id_devuelto,fecha_devuelto',
                    'prestamos.usuario:id_user,nombre,apellido,correo'
                ])
                ->when(!empty($search), fn($q) => 
                                $q->where('titulo', 'LIKE', "%$search%")
                                ->orWhere('id', 'LIKE', "%$search%")
                            )
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

    /**
     * Metodo para ver los ejemplares deshabilitados de un libro
     * @param mixed $id_libro
     * @param mixed $perpage
     * @return array{data: array, error: bool, message: string}
     */
    public function verEjemplaresDeshabilitadosLibroBiblioteca(?int $id_libro = null, ?int $perpage = 10)
    {
        try {
            $ejemplares = Ejemplares::query()
                ->where('estado', 4)
                ->with(['libro:id,titulo,autor,editorial,foto'])
                ->when(
                    !empty($id_libro),
                    fn($q) => $q->where('id_libro', $id_libro)
                )
                ->paginate($perpage);

            if ($ejemplares->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontraron ejemplares deshabilitados",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Ejemplares deshabilitados listados correctamente",
                'data' => $ejemplares->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error en el servidor al tratar de listar los ejemplares deshabilitados");
            return [
                'error' => true,
                "message" => "Error en el servidor al listar ejemplares deshabilitados",
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

    /**
     * Query base compartida por los préstamos activos y devueltos: mismos filtros
     * (búsqueda, curso, nivel, rango de fechas, código de ejemplar), solo cambia
     * si se exige que el préstamo esté devuelto o no.
     */
    private function filtrarPrestamosEjemplar(
        bool $devueltos,
        ?string $search,
        array|string|int|null $id_curso,
        array|string|int|null $id_nivel,
        ?string $fecha_inicio,
        ?string $fecha_fin,
        ?string $codigo_ejemplar
    ) {
        $id_curso = array_filter(Arr::wrap($id_curso));
        $id_nivel = array_filter(Arr::wrap($id_nivel));

        return PrestamosEjemplar::query()
            ->when(
                $devueltos,
                fn ($q) => $q->whereNotNull('id_devuelto'),
                fn ($q) => $q->whereNull(['id_devuelto', 'fecha_devuelto'])
            )
            ->with([
                'ejemplar:id,codigo,id_libro',
                'ejemplar.libro:id,titulo,autor,editorial,edicion,foto,id_categoria,id_subcategoria',
                'ejemplar.libro.categoria:id,nombre,activo',
                'ejemplar.libro.subcategoria:id,id_categoria,nombre,activo',
                'usuario:id_user,nombre,apellido,correo,id_curso,id_nivel',
                'usuario.cursoRelacion:id,nombre',
                'usuario.nivelRelacion:id,nombre',
            ])
            ->when(
                !empty($search),
                fn ($q) => $q->where(
                    fn ($grupo) => $grupo->whereHas(
                        'usuario',
                        fn ($sub) => $sub->where('nombre', 'LIKE', "%$search%")
                            ->orWhere('apellido', 'LIKE', "%$search%")
                    )->orWhereHas(
                        'ejemplar',
                        fn ($sub) => $sub->where('codigo', 'LIKE', "%$search%")
                    )
                )
            )
            ->when(
                !empty($id_curso) || !empty($id_nivel),
                fn ($q) => $q->whereHas(
                    'usuario',
                    fn ($sub) => $sub->when(!empty($id_curso), fn ($s) => $s->whereIn('id_curso', $id_curso))
                        ->when(!empty($id_nivel), fn ($s) => $s->whereIn('id_nivel', $id_nivel))
                )
            )
            ->when(
                !empty($codigo_ejemplar),
                fn ($q) => $q->whereHas(
                    'ejemplar',
                    fn ($sub) => $sub->where('codigo', 'LIKE', "%$codigo_ejemplar%")
                )
            )
            ->when(!empty($fecha_inicio), fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fecha_inicio))
            ->when(!empty($fecha_fin), fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fecha_fin))
            ->orderByDesc('fecha_prestamo');
    }

    /**
     * Lista todos los préstamos de ejemplar activos (no devueltos) de la biblioteca,
     * sin filtrar por usuario ni ejemplar específico.
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerPrestamosEjemplarActivos(
        ?string $search = null,
        ?int $perpage = 10,
        array|string|int|null $id_curso = null,
        array|string|int|null $id_nivel = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $codigo_ejemplar = null
    ): array
    {
        try {
            $prestamos = $this->filtrarPrestamosEjemplar(
                false,
                $search,
                $id_curso,
                $id_nivel,
                $fecha_inicio,
                $fecha_fin,
                $codigo_ejemplar
            )->paginate($perpage);

            if ($prestamos->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No hay préstamos activos',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Préstamos activos listados correctamente',
                'data' => $prestamos,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al tratar de listar los préstamos activos");
            return [
                'error' => true,
                'message' => "Error en el servidor al listar los préstamos activos",
                'data' => [],
            ];
        }
    }

    /**
     * Lista todos los préstamos de ejemplar ya devueltos de la biblioteca,
     * sin filtrar por usuario ni ejemplar específico.
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerPrestamosEjemplarDevueltos(
        ?string $search = null,
        ?int $perpage = 10,
        array|string|int|null $id_curso = null,
        array|string|int|null $id_nivel = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $codigo_ejemplar = null
    ): array
    {
        try {
            $prestamos = $this->filtrarPrestamosEjemplar(
                true,
                $search,
                $id_curso,
                $id_nivel,
                $fecha_inicio,
                $fecha_fin,
                $codigo_ejemplar
            )->paginate($perpage);

            if ($prestamos->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No hay préstamos devueltos',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Préstamos devueltos listados correctamente',
                'data' => $prestamos,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al tratar de listar los préstamos devueltos");
            return [
                'error' => true,
                'message' => "Error en el servidor al listar los préstamos devueltos",
                'data' => [],
            ];
        }
    }

    public function obtenerHistorialPrestamoEjemplarUsuario(
        ?int $id_usuario = null,
        bool $deuda = false,
        ?string $search = null,
        array|string|int|null $id_curso = null,
        array|string|int|null $id_nivel = null,
        string $dir = 'desc',
        ?int $perpage = 10
    )
    {
        $id_curso = array_filter(Arr::wrap($id_curso));
        $id_nivel = array_filter(Arr::wrap($id_nivel));
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        try {
            $historial = PrestamosEjemplar::with([
                'ejemplar:id,codigo,id_libro',
                'usuario:id_user,nombre,apellido,correo,documento,id_curso,id_nivel',
                'usuario.cursoRelacion:id,nombre',
                'usuario.nivelRelacion:id,nombre',
                'ejemplar.libro:id,titulo,editorial,edicion,autor,id_categoria,id_subcategoria',
                'ejemplar.libro.categoria:id,nombre',
                'ejemplar.libro.subcategoria:id,nombre',
            ])->when($id_usuario, fn ($q) => $q->where('id_usuario', $id_usuario))
                ->when($deuda, fn ($q) => $q->whereNull('id_devuelto'))
                ->when(
                    !empty($search),
                    fn ($q) => $q->whereHas(
                        'usuario',
                        fn ($sub) => $sub->where('nombre', 'LIKE', "%$search%")
                            ->orWhere('apellido', 'LIKE', "%$search%")
                            ->orWhere('documento', 'LIKE', "%$search%")
                    )
                )
                ->when(
                    !empty($id_curso) || !empty($id_nivel),
                    fn ($q) => $q->whereHas(
                        'usuario',
                        fn ($sub) => $sub->when(!empty($id_curso), fn ($s) => $s->whereIn('id_curso', $id_curso))
                            ->when(!empty($id_nivel), fn ($s) => $s->whereIn('id_nivel', $id_nivel))
                    )
                )
                ->orderBy('fecha_prestamo', $dir)
                ->paginate($perpage);

            if ($historial->isEmpty()) {
                return [
                    'error' => true,
                    'message' => $deuda
                        ? "El usuario no tiene préstamos pendientes"
                        : "No se encontraron historiales de préstamos",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Historial de prestamos obtenidos",
                'data' => $historial,
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener el historial de prestamos");
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener el historial de prestamos",
                'data' => []
            ];
        }
    }

    /**
     * Envía un correo recordatorio a los usuarios indicados que tengan préstamos
     * de ejemplares sin devolver, adjuntando el PDF de paz y salvo con el detalle.
     * @param array|string|int $ids_usuarios
     * @return array{data: array, error: bool, message: string}
     */
    public function enviarRecordatorioPrestamosPendientes(array|string|int $ids_usuarios): array
    {
        try {
            $ids_usuarios = array_filter(Arr::wrap($ids_usuarios));

            if (empty($ids_usuarios)) {
                return [
                    'error' => true,
                    'message' => "No se recibieron usuarios a notificar",
                    'data' => [],
                ];
            }

            $usuarios = Usuario::whereIn('id_user', $ids_usuarios)->get(['id_user', 'nombre', 'correo']);
            $mailService = app(MailService::class);

            $enviados = [];
            $sin_prestamos_pendientes = [];
            $sin_correo = [];

            foreach ($usuarios as $usuario) {
                $cantidadLibros = PrestamosEjemplar::where('id_usuario', $usuario->id_user)
                    ->whereNull('id_devuelto')
                    ->count();

                $cantidadPaquetes = PaquetePrestamos::where('id_usuario', $usuario->id_user)
                    ->whereNull('id_devuelto')
                    ->count();

                if ($cantidadLibros === 0 && $cantidadPaquetes === 0) {
                    $sin_prestamos_pendientes[] = $usuario->id_user;
                    continue;
                }

                // filter_var (no solo empty()) porque hay correos "basura" (ej. ".") que no
                // están vacíos pero MailService los descarta igual — sin este check el usuario
                // no caía en ningún bucket: el envío fallaba en silencio y no se reportaba.
                if (empty($usuario->correo) || filter_var($usuario->correo, FILTER_VALIDATE_EMAIL) === false) {
                    $sin_correo[] = $usuario->id_user;
                    continue;
                }

                $pdf = $this->generarPazYSalvoPdf($usuario->id_user);

                if ($pdf['error']) {
                    continue;
                }

                $enviado = $mailService->send($usuario->correo, new RecordatorioPrestamosEmail(
                    $usuario->nombre,
                    $cantidadLibros,
                    $pdf['data']['contenido'],
                    $pdf['data']['nombre_archivo'],
                    $cantidadPaquetes
                ));

                if ($enviado) {
                    $enviados[] = $usuario->id_user;
                }
            }

            return [
                'error' => false,
                'message' => "Recordatorios procesados",
                'data' => [
                    'enviados' => $enviados,
                    'sin_prestamos_pendientes' => $sin_prestamos_pendientes,
                    'sin_correo' => $sin_correo,
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al enviar los recordatorios de préstamos");
            return [
                'error' => true,
                'message' => "Error en el servidor al enviar los recordatorios de préstamos",
                'data' => [],
            ];
        }
    }

    /**
     * Envía por correo el PDF de paz y salvo a los usuarios indicados (o a todos
     * los usuarios activos si no se especifican) que tengan al menos un registro de
     * préstamo histórico (libro o paquete, devuelto o no) — quien nunca ha usado la
     * biblioteca no necesita un paz y salvo y se excluye. Entre los que sí califican,
     * se envía sin importar si tienen o no préstamos pendientes por devolver.
     * @param array|string|int $ids_usuarios
     * @return array{data: array, error: bool, message: string}
     */
    public function enviarPazYSalvoGlobal(array|string|int $ids_usuarios = [], array|string|int $cursos = []): array
    {
        try {
            $ids_usuarios = array_filter(Arr::wrap($ids_usuarios));
            $cursos = array_filter(Arr::wrap($cursos));

            $query = Usuario::query();
            if (!empty($ids_usuarios)) {
                $query->whereIn('id_user', $ids_usuarios);
            } elseif (!empty($cursos)) {
                $query->where('estado', 'activo')->whereIn('id_curso', $cursos);
            } else {
                $query->where('estado', 'activo');
            }
            $usuarios = $query->get(['id_user', 'nombre', 'correo']);

            $mailService = app(MailService::class);

            $enviados = [];
            $sin_prestamos_pendientes = [];
            $sin_historial_prestamos = [];
            $sin_correo = [];

            foreach ($usuarios as $usuario) {
                // filter_var (no solo empty()) porque hay correos "basura" (ej. ".") que no
                // están vacíos pero MailService los descarta igual — sin este check el usuario
                // no caía en ningún bucket: el envío fallaba en silencio y no se reportaba.
                if (empty($usuario->correo) || filter_var($usuario->correo, FILTER_VALIDATE_EMAIL) === false) {
                    $sin_correo[] = $usuario->id_user;
                    continue;
                }

                $tieneHistorialLibros = PrestamosEjemplar::where('id_usuario', $usuario->id_user)->exists();
                $tieneHistorialPaquetes = PaquetePrestamos::where('id_usuario', $usuario->id_user)->exists();

                if (!$tieneHistorialLibros && !$tieneHistorialPaquetes) {
                    $sin_historial_prestamos[] = $usuario->id_user;
                    continue;
                }

                $cantidadLibros = PrestamosEjemplar::where('id_usuario', $usuario->id_user)
                    ->whereNull('id_devuelto')
                    ->count();

                $cantidadPaquetes = PaquetePrestamos::where('id_usuario', $usuario->id_user)
                    ->whereNull('id_devuelto')
                    ->count();

                if ($cantidadLibros === 0 && $cantidadPaquetes === 0) {
                    $sin_prestamos_pendientes[] = $usuario->id_user;
                }

                $pdf = $this->generarPazYSalvoPdf($usuario->id_user);

                if ($pdf['error']) {
                    continue;
                }

                $enviado = $mailService->send($usuario->correo, new RecordatorioPrestamosEmail(
                    $usuario->nombre,
                    $cantidadLibros,
                    $pdf['data']['contenido'],
                    $pdf['data']['nombre_archivo'],
                    $cantidadPaquetes
                ));

                if ($enviado) {
                    $enviados[] = $usuario->id_user;
                }
            }

            return [
                'error' => false,
                'message' => "Paz y salvo enviado",
                'data' => [
                    'enviados' => $enviados,
                    'sin_prestamos_pendientes' => $sin_prestamos_pendientes,
                    'sin_historial_prestamos' => $sin_historial_prestamos,
                    'sin_correo' => $sin_correo,
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al enviar el paz y salvo global");
            return [
                'error' => true,
                'message' => "Error en el servidor al enviar el paz y salvo global",
                'data' => [],
            ];
        }
    }

    /**
     * Genera el PDF de "Paz y Salvo" con los libros Y paquetes que el usuario aún
     * no ha devuelto (ambos, no solo libros — un paz y salvo real debe reflejar
     * cualquier préstamo pendiente). Se genera en memoria, no se guarda en el servidor.
     * @param int $id_usuario
     * @return array{data: array, error: bool, message: string}
     */
    public function generarPazYSalvoPdf(int $id_usuario): array
    {
        try {
            $usuario = Usuario::select(['id_user', 'nombre', 'apellido', 'documento'])->find($id_usuario);

            if (!$usuario) {
                return [
                    'error' => true,
                    'message' => "No se encontró el usuario con ID: {$id_usuario}",
                    'data' => [],
                ];
            }

            $prestamosLibros = PrestamosEjemplar::with([
                'ejemplar:id,codigo,id_libro',
                'ejemplar.libro:id,titulo,id_categoria,id_subcategoria',
                'ejemplar.libro.categoria:id,nombre',
                'ejemplar.libro.subcategoria:id,nombre',
            ])->where('id_usuario', $id_usuario)
                ->whereNull('id_devuelto')
                ->orderBy('fecha_prestamo', 'desc')
                ->get();

            $prestamosPaquetes = PaquetePrestamos::with(['paquete:id,nombre,codigo'])
                ->where('id_usuario', $id_usuario)
                ->whereNull('id_devuelto')
                ->orderBy('fecha_prestamo', 'desc')
                ->get();

            // Se renumera "no_prestamo" tras fusionar para que la tabla quede correlativa (1..n), sin huecos ni duplicados.
            $filas = array_merge(
                $this->construirFilasPrestamosPdf($prestamosLibros),
                $this->construirFilasPaquetesPdf($prestamosPaquetes)
            );
            foreach ($filas as $indice => &$fila) {
                $fila['no_prestamo'] = (string) ($indice + 1);
            }
            unset($fila);

            $pdfService = app(PazYSalvoPdfService::class);
            $contenido = $pdfService->generate([
                'tipo' => 'paz_y_salvo',
                'institucion' => config('app.name'),
                'nombre_trabajador' => trim("{$usuario->nombre} {$usuario->apellido}"),
                'numero_documento' => (string) $usuario->documento,
                'prestamos' => $filas,
                'sin_pendientes' => $prestamosLibros->isEmpty() && $prestamosPaquetes->isEmpty(),
            ]);

            return [
                'error' => false,
                'message' => "PDF generado correctamente",
                'data' => [
                    'contenido' => $contenido,
                    'nombre_archivo' => "paz_y_salvo_{$id_usuario}.pdf",
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al generar el PDF de paz y salvo");
            return [
                'error' => true,
                'message' => "Error en el servidor al generar el PDF de paz y salvo",
                'data' => [],
            ];
        }
    }

    /**
     * Genera el PDF "Listado de Prestamos" con todos los préstamos (devueltos
     * o no) que ha realizado el usuario. Se genera en memoria, no se guarda
     * en el servidor.
     * @param int $id_usuario
     * @return array{data: array, error: bool, message: string}
     */
    public function generarListadoPrestamosPdf(int $id_usuario): array
    {
        try {
            $usuario = Usuario::select(['id_user', 'nombre', 'apellido', 'documento'])->find($id_usuario);

            if (!$usuario) {
                return [
                    'error' => true,
                    'message' => "No se encontró el usuario con ID: {$id_usuario}",
                    'data' => [],
                ];
            }

            $prestamos = PrestamosEjemplar::with([
                'ejemplar:id,codigo,id_libro',
                'ejemplar.libro:id,titulo,id_categoria,id_subcategoria',
                'ejemplar.libro.categoria:id,nombre',
                'ejemplar.libro.subcategoria:id,nombre',
            ])->where('id_usuario', $id_usuario)
                ->orderBy('fecha_prestamo', 'desc')
                ->get();

            $pdfService = app(PazYSalvoPdfService::class);
            $contenido = $pdfService->generate([
                'tipo' => 'listado',
                'institucion' => config('app.name'),
                'nombre_trabajador' => trim("{$usuario->nombre} {$usuario->apellido}"),
                'numero_documento' => (string) $usuario->documento,
                'prestamos' => $this->construirFilasPrestamosPdf($prestamos),
            ]);

            return [
                'error' => false,
                'message' => "PDF generado correctamente",
                'data' => [
                    'contenido' => $contenido,
                    'nombre_archivo' => "listado_prestamos_{$id_usuario}.pdf",
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al generar el PDF de listado de préstamos");
            return [
                'error' => true,
                'message' => "Error en el servidor al generar el PDF de listado de préstamos",
                'data' => [],
            ];
        }
    }

    /**
     * Estado de un préstamo (libro o paquete) para las tablas de PazYSalvoPdfService:
     * ya devuelto -> a tiempo/antes/tarde comparando fecha_devuelto vs fecha_devolucion;
     * no devuelto -> vencido/por vencer comparando fecha_devolucion vs hoy.
     */
    private function calcularEstadoPrestamo($idDevuelto, $fechaDevolucion, $fechaDevuelto, $hoy): string
    {
        if ($idDevuelto) {
            if ($fechaDevolucion && $fechaDevuelto) {
                $entrega = $fechaDevuelto->toDateString();
                $limite = $fechaDevolucion->toDateString();
                return match (true) {
                    $entrega < $limite => 'DEVUELTO ANTES DE TIEMPO',
                    $entrega > $limite => 'DEVOLUCION TARDIA',
                    default => 'JUSTO A TIEMPO',
                };
            }
            return 'JUSTO A TIEMPO';
        }

        $vencido = $fechaDevolucion && $fechaDevolucion->lt($hoy);
        return $vencido ? 'VENCIDO' : 'POR VENCER';
    }

    /**
     * Mapea préstamos de ejemplar al formato de fila que espera PazYSalvoPdfService.
     * @param \Illuminate\Support\Collection<int, PrestamosEjemplar> $prestamos
     */
    private function construirFilasPrestamosPdf($prestamos): array
    {
        $hoy = now()->startOfDay();

        return $prestamos->values()->map(function ($prestamo, $indice) use ($hoy) {
            return [
                'no_prestamo' => (string) ($indice + 1),
                'libro' => $prestamo->ejemplar->libro->titulo ?? '-',
                'num_ejemplar' => $prestamo->ejemplar->codigo ?? '-',
                'categoria' => $prestamo->ejemplar->libro->categoria->nombre ?? '-',
                'subcategoria' => $prestamo->ejemplar->libro->subcategoria->nombre ?? '-',
                'fecha_prestamo' => $prestamo->fecha_prestamo?->format('d/m/Y') ?? '-',
                'fecha_devolucion' => $prestamo->fecha_devolucion?->format('d/m/Y') ?? '-',
                'estado' => $this->calcularEstadoPrestamo($prestamo->id_devuelto, $prestamo->fecha_devolucion, $prestamo->fecha_devuelto, $hoy),
            ];
        })->all();
    }

    /**
     * Mapea préstamos de paquete al formato de fila de construirFilasPrestamosPdf (libros)
     * para que ambos puedan fusionarse en una sola tabla de paz y salvo — 'categoria' se
     * usa como indicador de tipo ("Paquete") ya que la tabla de paz_y_salvo/listado no
     * tiene una columna de tipo propia y no se quiso alterar su layout medido a pixel.
     * @param \Illuminate\Support\Collection<int, PaquetePrestamos> $prestamos
     */
    private function construirFilasPaquetesPdf($prestamos): array
    {
        $hoy = now()->startOfDay();

        return $prestamos->values()->map(function ($prestamo) use ($hoy) {
            return [
                'no_prestamo' => '', // se renumera al fusionar con los de libros en generarPazYSalvoPdf
                'libro' => $prestamo->paquete->nombre ?? '-',
                'num_ejemplar' => $prestamo->paquete->codigo ?? '-',
                'categoria' => 'Paquete',
                'subcategoria' => '-',
                'fecha_prestamo' => $prestamo->fecha_prestamo?->format('d/m/Y') ?? '-',
                'fecha_devolucion' => $prestamo->fecha_devolucion?->format('d/m/Y') ?? '-',
                'estado' => $this->calcularEstadoPrestamo($prestamo->id_devuelto, $prestamo->fecha_devolucion, $prestamo->fecha_devuelto, $hoy),
            ];
        })->all();
    }

    /**
     * Genera el PDF "Listado de Prestamos (Paquetes)" con todos los préstamos de
     * paquetes (devueltos o no) que ha realizado el usuario. Mismo estilo que
     * generarListadoPrestamosPdf (libros), vía PazYSalvoPdfService::generate con
     * tipo 'listado_paquetes'.
     * @param int $id_usuario
     * @return array{data: array, error: bool, message: string}
     */
    public function generarListadoPrestamosPaquetesPdf(int $id_usuario): array
    {
        try {
            $usuario = Usuario::select(['id_user', 'nombre', 'apellido', 'documento', 'id_curso'])
                ->with('cursoRelacion:id,nombre')
                ->find($id_usuario);

            if (!$usuario) {
                return [
                    'error' => true,
                    'message' => "No se encontró el usuario con ID: {$id_usuario}",
                    'data' => [],
                ];
            }

            $prestamos = PaquetePrestamos::with(['paquete:id,nombre,codigo'])
                ->where('id_usuario', $id_usuario)
                ->orderBy('fecha_prestamo', 'desc')
                ->get();

            $pdfService = app(PazYSalvoPdfService::class);
            $contenido = $pdfService->generate([
                'tipo' => 'listado_paquetes',
                'institucion' => config('app.name'),
                'nombre_trabajador' => trim("{$usuario->nombre} {$usuario->apellido}"),
                'numero_documento' => (string) $usuario->documento,
                'curso' => $usuario->cursoRelacion->nombre ?? '',
                'prestamos' => $this->construirFilasPrestamosPaquetesPdf($prestamos),
            ]);

            return [
                'error' => false,
                'message' => "PDF generado correctamente",
                'data' => [
                    'contenido' => $contenido,
                    'nombre_archivo' => "listado_prestamos_paquetes_{$id_usuario}.pdf",
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al generar el PDF de listado de préstamos de paquetes");
            return [
                'error' => true,
                'message' => "Error en el servidor al generar el PDF de listado de préstamos de paquetes",
                'data' => [],
            ];
        }
    }

    /**
     * Mapea préstamos de paquete al formato de fila que espera PazYSalvoPdfService
     * (tipo 'listado_paquetes').
     * @param \Illuminate\Support\Collection<int, PaquetePrestamos> $prestamos
     */
    private function construirFilasPrestamosPaquetesPdf($prestamos): array
    {
        $hoy = now()->startOfDay();

        return $prestamos->values()->map(function ($prestamo, $indice) use ($hoy) {
            return [
                'no_prestamo' => (string) ($indice + 1),
                'paquete' => $prestamo->paquete->nombre ?? '-',
                'codigo' => $prestamo->paquete->codigo ?? '-',
                'fecha_prestamo' => $prestamo->fecha_prestamo?->format('d/m/Y') ?? '-',
                'fecha_devolucion' => $prestamo->fecha_devolucion?->format('d/m/Y') ?? '-',
                'estado' => $this->calcularEstadoPrestamo($prestamo->id_devuelto, $prestamo->fecha_devolucion, $prestamo->fecha_devuelto, $hoy),
            ];
        })->all();
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
            $paquetes = Paquetes::with([
                'contenidos' => fn($q) => $q->where('activo', 1)
            ])->where('activo', 1)
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

    public function cambiarEstadoPaqueteBiblioteca(array $ids_paquetes, int $estado)
    {
        try {
            $paquetes = Paquetes::whereIn('id', $ids_paquetes);

            if (!in_array($estado, [0, 1])) {
                return [
                    'error' => true,
                    'message' => 'Estado inválido',
                    'data' => []
                ];
            }

            if (!$paquetes->exists()) {
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
        } catch (Exception $e) {
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

    public function agregarContenidoPaqueteBiblioteca(array $data)
    {
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
        } catch (Exception $e) {

            $this->sendError($e, "Error al crear el contenido");

            return [
                'error' => true,
                'message' => "Error en el servidor al crear el contenido",
                'data' => []
            ];
        }
    }

    public function cambiarEstadoContenidoPaqueteBiblioteca(array $ids, int $id_paquete, int $estado)
    {
        try {
            $contenidos = PaqueteContenido::whereIn('id', $ids)->where('id_paquete', $id_paquete);

            if (!$contenidos->exists()) {
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
        } catch (Exception $e) {
            $this->sendError($e, "Error al actualizar el estado del contenido");
            return [
                'error' => true,
                'message' => "Error en el servidor al actualizar el estado del contenido",
                'data' => []
            ];
        }
    }

    /**
     * Elimina en lote contenido de un paquete (borrado real, no desactivación).
     * Scoped por id_paquete, igual que cambiarEstadoContenidoPaqueteBiblioteca.
     */
    public function eliminarContenidoPaqueteBiblioteca(array $ids, int $id_paquete)
    {
        try {
            $contenidos = PaqueteContenido::whereIn('id', $ids)->where('id_paquete', $id_paquete);

            if (!$contenidos->exists()) {
                return [
                    'error' => true,
                    'message' => "No se encontró el contenido a eliminar",
                    'data' => []
                ];
            }

            $eliminados = $contenidos->delete();

            return [
                'error' => false,
                'message' => "Contenido eliminado correctamente",
                'data' => ["eliminados" => $eliminados],
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al eliminar el contenido");
            return [
                'error' => true,
                'message' => "Error en el servidor al eliminar el contenido",
                'data' => []
            ];
        }
    }

    public function editarDatosContenidoPaqueteBiblioteca(array $data, int $id_contenido)
    {
        try {
            $contenido = PaqueteContenido::find($id_contenido);

            if (!$contenido) {
                return [
                    'error' => true,
                    'message' => "No se ha encontrado el contenido con el ID: " . $id_contenido,
                    'data' => []
                ];
            }

            $contenidoUpdate = $contenido->update($data);

            if (!$contenidoUpdate) {
                return [
                    'error' => true,
                    'message' => "No se ha actualizado el contenido",
                    'data' => $contenido->toArray()
                ];
            }

            return [
                'error' => false,
                'message' => "Contenido actualizado correctamente",
                'data' => $contenido->refresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al actualizar el contenido");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de actualizar el contenido",
                'data' => []
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |   BIBLIOTECA CONTENIDO PAQUETES PRESTAMOS
    |
    -------------------------------------------------
    */
    public function mostrarHistorialPrestamosPaquetesUsuario(int $id_usuario, bool $deuda = false)
    {
        try {
            $historial = PaquetePrestamos::with([
                'usuario:id_user,nombre,apellido,correo',
                'paquete:id,nombre,codigo',
                'devuelto:id_user,nombre,apellido,correo'
            ])
                ->where('id_usuario', $id_usuario)
                ->when($deuda, fn($q) => $q->whereNull('id_devuelto'))
                ->get();

            if ($historial->isEmpty()) {
                return [
                    'error' => true,
                    'message' => $deuda
                        ? "El usuario no tiene préstamos pendientes"
                        : "No se encontraron historiales de préstamos",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Historial de prestamos obtenidos",
                'data' => $historial->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener el historial de prestamos");

            return [
                'error' => true,
                'message' => "Error en el servidor al obtener el historial de prestamos",
                'data' => []
            ];
        }
    }

    /**
     * Verifica un paquete por código (mismo rol que verificarEjemplarBiblioteca):
     * confirma existencia y disponibilidad antes de generar el préstamo.
     */
    public function verificarPaqueteBiblioteca(string $codigo): array
    {
        try {
            $paquete = Paquetes::where('codigo', $codigo)->first();

            if (!$paquete) {
                return [
                    'error' => true,
                    'message' => 'El paquete no existe',
                    'data' => ['existe' => false],
                ];
            }

            $disponible = $paquete->estado == 1 && $paquete->activo == 1;

            return [
                'error' => false,
                'message' => $disponible
                    ? 'El paquete está disponible para préstamo'
                    : 'El paquete existe pero no está disponible para préstamo',
                'data' => [
                    'existe' => true,
                    'disponible' => $disponible,
                    'estado' => $paquete->estado,
                    'paquete' => $paquete,
                ],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, "Error al tratar de verificar el paquete");
            return [
                'error' => true,
                'message' => "Error en el servidor al verificar el paquete",
                'data' => [],
            ];
        }
    }

    public function generarPrestamoPaqueteUsuario(int $id_usuario, string $codigo_paquete, string $fecha_devolucion, ?string $observacion = null)
    {
        try {

            $prestamo = DB::transaction(function () use (
                $id_usuario,
                $codigo_paquete,
                $fecha_devolucion,
                $observacion
            ) {
                $paquete = Paquetes::where('codigo', $codigo_paquete)->lockForUpdate()->first();

                if (!$paquete) {
                    throw new Exception("No se encontró el paquete con ese código");
                }

                if ($paquete->estado == 2) {
                    throw new Exception("El paquete ya se encuentra prestado");
                }

                $prestamo = PaquetePrestamos::create([
                    'id_paquete'        => $paquete->id,
                    'id_usuario'        => $id_usuario,
                    'fecha_prestamo'    => now(),
                    'fecha_devolucion'  => $fecha_devolucion,
                    'observacion'       => $observacion,
                    'id_log'            => Auth::id(),
                ]);

                $paquete->update([
                    'estado' => 2
                ]);

                return $prestamo;
            });

            return [
                'error' => false,
                'message' => 'Préstamo generado correctamente',
                'data' => $prestamo->toArray()
            ];
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function devolverPrestamoPaqueteUsuario(string $codigo_paquete, ?string $observacion = null)
    {
        try {
            $prestamo = DB::transaction(function () use (
                $codigo_paquete,
                $observacion
            ) {

                $paquete = Paquetes::where('codigo', $codigo_paquete)->lockForUpdate()->first();

                if (!$paquete) {
                    throw new Exception("No se encontró el paquete con ese código");
                }

                if ($paquete->estado == 1) {
                    throw new Exception("Este paquete no se encuentra en préstamo");
                }

                $prestamo = PaquetePrestamos::where('id_paquete', $paquete->id)
                    ->whereNull('id_devuelto')
                    ->lockForUpdate()
                    ->first();

                if (!$prestamo) {
                    throw new Exception("No se encontró un préstamo activo para este paquete");
                }

                $prestamo->update([
                    'observacion' => $observacion,
                    'id_devuelto' => Auth::id(),
                    'fecha_devuelto' => now()
                ]);

                $paquete->update([
                    'estado' => 1
                ]);

                return $prestamo;
            });

            return [
                'error' => false,
                'message' => 'Préstamo devuelto correctamente',
                'data' => $prestamo->toArray()
            ];

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             METRICAS BIBLIOTECA
    |
    -------------------------------------------------
    */

    /**
     * KPI de paquetes para la pestaña "Paquetes" de Métricas — análogo a
     * estadisticasEjemplaresBiblioteca (libros).
     */
    public function estadisticasPaquetesBiblioteca(): array
    {
        try {
            $counts = Paquetes::selectRaw('estado, count(*) as total')
                ->where('activo', 1)
                ->groupBy('estado')
                ->pluck('total', 'estado');

            return [
                'error' => false,
                'message' => 'Estadísticas obtenidas',
                'data' => [
                    'disponibles' => (int) ($counts[1] ?? 0),
                    'prestados' => (int) ($counts[2] ?? 0),
                    'paquetes_total' => Paquetes::count(),
                    'contenido_total' => PaqueteContenido::where('activo', 1)->count(),
                    'paquetes_inactivos' => Paquetes::where('activo', 0)->count(),
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener estadísticas de paquetes');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas de paquetes',
                'data' => [],
            ];
        }
    }

    /** Libros con más préstamos en el rango de fechas dado. */
    public function librosMasPrestados(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $prestamos = PrestamosEjemplar::with(['ejemplar:id,id_libro', 'ejemplar.libro:id,titulo'])
                ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->get()
                ->filter(fn ($p) => $p->ejemplar?->libro);

            $resultado = $prestamos->groupBy(fn ($p) => $p->ejemplar->id_libro)
                ->map(fn ($grupo) => [
                    'libro_id' => $grupo->first()->ejemplar->id_libro,
                    'libro_titulo' => $grupo->first()->ejemplar->libro->titulo,
                    'total_prestamos' => $grupo->count(),
                ])
                ->sortByDesc('total_prestamos')
                ->take($limite)
                ->values();

            return [
                'error' => false,
                'message' => 'Libros más prestados obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener libros más prestados');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener libros más prestados',
                'data' => [],
            ];
        }
    }

    /** Categorías de libro con más préstamos en el rango de fechas dado. */
    public function categoriasLibrosMasPrestadas(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $prestamos = PrestamosEjemplar::with(['ejemplar:id,id_libro', 'ejemplar.libro:id,id_categoria', 'ejemplar.libro.categoria:id,nombre'])
                ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->get()
                ->filter(fn ($p) => $p->ejemplar?->libro?->categoria);

            $resultado = $prestamos->groupBy(fn ($p) => $p->ejemplar->libro->id_categoria)
                ->map(fn ($grupo) => [
                    'categoria_id' => $grupo->first()->ejemplar->libro->id_categoria,
                    'categoria_nombre' => $grupo->first()->ejemplar->libro->categoria->nombre,
                    'total_prestamos' => $grupo->count(),
                ])
                ->sortByDesc('total_prestamos')
                ->take($limite)
                ->values();

            return [
                'error' => false,
                'message' => 'Categorías más prestadas obtenidas',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener categorías más prestadas');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener categorías más prestadas',
                'data' => [],
            ];
        }
    }

    /** Cursos con más préstamos de libros en el rango de fechas dado. */
    public function cursosConMasPrestamosLibros(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $usuariosConPrestamo = PrestamosEjemplar::when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->pluck('id_usuario');

            $cursos = \App\Models\Areas\Cursos::select('id', 'nombre')
                ->whereIn('id', fn ($q) => $q->select('id_curso')
                    ->from('usuarios')
                    ->whereIn('id_user', $usuariosConPrestamo))
                ->get();

            $resultado = [];
            foreach ($cursos as $curso) {
                $usuariosDelCurso = Usuario::where('id_curso', $curso->id)
                    ->whereIn('id_user', $usuariosConPrestamo)
                    ->pluck('id_user');

                $totalPrestamos = PrestamosEjemplar::whereIn('id_usuario', $usuariosDelCurso)
                    ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                    ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                    ->count();

                $resultado[] = [
                    'curso_id' => $curso->id,
                    'curso_nombre' => $curso->nombre,
                    'total_prestamos' => $totalPrestamos,
                    'usuarios_con_prestamo' => $usuariosDelCurso->count(),
                ];
            }

            usort($resultado, fn ($a, $b) => $b['total_prestamos'] <=> $a['total_prestamos']);
            $resultado = array_slice($resultado, 0, $limite);

            return [
                'error' => false,
                'message' => 'Cursos con más préstamos de libros obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener cursos con más préstamos de libros');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener cursos con más préstamos de libros',
                'data' => [],
            ];
        }
    }

    /** Paquetes con más préstamos en el rango de fechas dado. */
    public function paquetesMasPrestados(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $resultado = PaquetePrestamos::select('id_paquete', DB::raw('COUNT(*) as total_prestamos'))
                ->with('paquete:id,nombre,codigo')
                ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->groupBy('id_paquete')
                ->orderByDesc('total_prestamos')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Paquetes más prestados obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener paquetes más prestados');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener paquetes más prestados',
                'data' => [],
            ];
        }
    }

    /** Cursos con más préstamos de paquetes en el rango de fechas dado. */
    public function cursosConMasPrestamosPaquetes(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $usuariosConPrestamo = PaquetePrestamos::when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->pluck('id_usuario');

            $cursos = \App\Models\Areas\Cursos::select('id', 'nombre')
                ->whereIn('id', fn ($q) => $q->select('id_curso')
                    ->from('usuarios')
                    ->whereIn('id_user', $usuariosConPrestamo))
                ->get();

            $resultado = [];
            foreach ($cursos as $curso) {
                $usuariosDelCurso = Usuario::where('id_curso', $curso->id)
                    ->whereIn('id_user', $usuariosConPrestamo)
                    ->pluck('id_user');

                $totalPrestamos = PaquetePrestamos::whereIn('id_usuario', $usuariosDelCurso)
                    ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                    ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                    ->count();

                $resultado[] = [
                    'curso_id' => $curso->id,
                    'curso_nombre' => $curso->nombre,
                    'total_prestamos' => $totalPrestamos,
                    'usuarios_con_prestamo' => $usuariosDelCurso->count(),
                ];
            }

            usort($resultado, fn ($a, $b) => $b['total_prestamos'] <=> $a['total_prestamos']);
            $resultado = array_slice($resultado, 0, $limite);

            return [
                'error' => false,
                'message' => 'Cursos con más préstamos de paquetes obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener cursos con más préstamos de paquetes');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener cursos con más préstamos de paquetes',
                'data' => [],
            ];
        }
    }

    public function perfilesConMasPrestamosLibros(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $usuariosConPrestamo = PrestamosEjemplar::when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->pluck('id_usuario');

            $perfiles = Perfil::select('id_perfil', 'nombre')
                ->whereIn('id_perfil', fn ($q) => $q->select('perfil')
                    ->from('usuarios')
                    ->whereIn('id_user', $usuariosConPrestamo))
                ->get();

            $resultado = [];
            foreach ($perfiles as $perfil) {
                $usuariosDelPerfil = Usuario::where('perfil', $perfil->id_perfil)
                    ->whereIn('id_user', $usuariosConPrestamo)
                    ->pluck('id_user');

                $totalPrestamos = PrestamosEjemplar::whereIn('id_usuario', $usuariosDelPerfil)
                    ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                    ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                    ->count();

                $resultado[] = [
                    'perfil_id' => $perfil->id_perfil,
                    'perfil_nombre' => $perfil->nombre,
                    'total_prestamos' => $totalPrestamos,
                    'usuarios_con_prestamo' => $usuariosDelPerfil->count(),
                ];
            }

            usort($resultado, fn ($a, $b) => $b['total_prestamos'] <=> $a['total_prestamos']);
            $resultado = array_slice($resultado, 0, $limite);

            return [
                'error' => false,
                'message' => 'Perfiles con más préstamos de libros obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener perfiles con más préstamos de libros');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener perfiles con más préstamos de libros',
                'data' => [],
            ];
        }
    }

    public function perfilesConMasPrestamosPaquetes(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $usuariosConPrestamo = PaquetePrestamos::when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                ->pluck('id_usuario');

            $perfiles = Perfil::select('id_perfil', 'nombre')
                ->whereIn('id_perfil', fn ($q) => $q->select('perfil')
                    ->from('usuarios')
                    ->whereIn('id_user', $usuariosConPrestamo))
                ->get();

            $resultado = [];
            foreach ($perfiles as $perfil) {
                $usuariosDelPerfil = Usuario::where('perfil', $perfil->id_perfil)
                    ->whereIn('id_user', $usuariosConPrestamo)
                    ->pluck('id_user');

                $totalPrestamos = PaquetePrestamos::whereIn('id_usuario', $usuariosDelPerfil)
                    ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_prestamo', '>=', $fechaDesde))
                    ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_prestamo', '<=', $fechaHasta))
                    ->count();

                $resultado[] = [
                    'perfil_id' => $perfil->id_perfil,
                    'perfil_nombre' => $perfil->nombre,
                    'total_prestamos' => $totalPrestamos,
                    'usuarios_con_prestamo' => $usuariosDelPerfil->count(),
                ];
            }

            usort($resultado, fn ($a, $b) => $b['total_prestamos'] <=> $a['total_prestamos']);
            $resultado = array_slice($resultado, 0, $limite);

            return [
                'error' => false,
                'message' => 'Perfiles con más préstamos de paquetes obtenidos',
                'data' => $resultado,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener perfiles con más préstamos de paquetes');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener perfiles con más préstamos de paquetes',
                'data' => [],
            ];
        }
    }
}

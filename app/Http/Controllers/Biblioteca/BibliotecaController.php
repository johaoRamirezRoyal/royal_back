<?php

namespace App\Http\Controllers\Biblioteca;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biblioteca\CategoriaRequest;
use App\Http\Requests\Biblioteca\ContenidoPaqueteRequest;
use App\Http\Requests\Biblioteca\EjemplaresRequest;
use App\Http\Requests\Biblioteca\LibroRequest;
use App\Http\Requests\Biblioteca\PaqueteRequest;
use App\Http\Requests\Biblioteca\PrestamoEjemplarRequest;
use App\Http\Requests\Biblioteca\SubcategoriaRequest;
use App\Models\Biblioteca\Libro;
use App\Services\Biblioteca\BibliotecaServices;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BibliotecaController extends Controller

{
    protected BibliotecaServices $biblioteca_services;
    protected FileStorageService $file_storage_service;

    public function __construct(BibliotecaServices $bibliotecaServices, FileStorageService $fileStorage)
    {
        $this->biblioteca_services = $bibliotecaServices;

        $this->file_storage_service = $fileStorage;
    }

    // CATEGORIAS
    public function mostrarCategoriasBiblioteca()
    {

        $response = $this->biblioteca_services->mostrarCategoriasBiblioteca();

        return $this->apiResponse($response);
    }

    public function cambiarEstadoCategoriaBiblioteca(Request $request)
    {
        $id_categoria = $request->input("id_categoria");
        $estado = $request->input("estado");

        $response = $this->biblioteca_services->cambiarEstadoCategoriaBiblioteca($id_categoria, $estado);

        return $this->apiResponse($response);
    }

    public function mostrarSubcategoriasBiblioteca(Request $request)
    {
        
        $id_categoria = $request->input("id_categoria", null);

        $response = $this->biblioteca_services->mostrarSubcategoriasBiblioteca($id_categoria);

        return $this->apiResponse($response);
    }

    public function agregarCategoriaBiblioteca(CategoriaRequest $request)
    {
        $r = $request->validated();

        $response = $this->biblioteca_services->agregarCategoriaBiblioteca($r);

        return $this->apiResponse($response);
    }

    public function cambiarEstadoSubcategoriaBiblioteca(Request $request)
    {
        $id_subcategoria = $request->input("id_subcategoria");
        $estado = $request->input("estado");

        $response = $this->biblioteca_services->cambiarEstadoSubcategoriaBiblioteca($id_subcategoria, $estado);
        return $this->apiResponse($response);
    }

    public function agregarSubcategoriaBiblioteca(SubcategoriaRequest $request)
    {
        $r = $request->validated();

        $response = $this->biblioteca_services->agregarSubcategoriaBiblioteca($r);

        return $this->apiResponse($response);
    }

    // LIBROS
    public function obtenerTodosLosLibrosBiblioteca(Request $request)
    {
        $response = $this->biblioteca_services
            ->obtenerTodosLosLibrosBiblioteca(
                $request->input('s'),
                $request->input('categoria'),
                $request->input('subcategoria'),
                $request->input('activo', 1),
                $request->input("per-page", 10),
            );

        return $this->apiResponse($response);
    }

    public function verImagenBiblioteca(string $carpeta, string $filename)
    {
        $ruta = storage_path("app/public/{$carpeta}/{$filename}");

        if (!file_exists($ruta)) {
            abort(404);
        }

        return response()->file($ruta, [
            'Content-Type' => mime_content_type($ruta)
        ]);
    }

    public function subirImagenBiblioteca(Request $request)
    {
        if (!$request->hasFile('foto')) {
            return $this->apiResponse([
                'error'   => true,
                'message' => 'No se recibió ningún archivo',
                'data'    => []
            ]);
        }

        $id_libro = $request->integer('id_libro') ?: null;
        $libro    = $id_libro ? Libro::find($id_libro) : null;

        if ($id_libro && !$libro) {
            return $this->apiResponse([
                'error'   => true,
                'message' => "No se encontró el libro con ID: {$id_libro}",
                'data'    => []
            ]);
        }

        if ($libro?->foto) {
            $this->file_storage_service->eliminar($libro->foto);
        }

        $archivo = $this->file_storage_service->uploadFile(
            $request->file('foto'),
            $request->input('carpeta', 'biblioteca')
        );

        if ($libro) {
            $libro->update(['foto' => $archivo['ruta']]);
        }

        return $this->apiResponse([
            'error'   => false,
            'message' => $libro ? 'Imagen subida y libro actualizado' : 'Imagen subida correctamente',
            'data'    => $archivo
        ]);
    }

    public function agregarNuevoLibroBiblioteca(LibroRequest $request)
    {
        $body = $request->validated();

        if ($request->hasFile('foto')) {

            $archivo = $this->file_storage_service->uploadFile(
                $request->file('foto'),
                'biblioteca'
            );

            $body['foto'] = $archivo['ruta'];
        }

        $response = $this->biblioteca_services->agregarNuevoLibroBiblioteca($body);

        return $this->apiResponse($response);
    }

    public function editarLibro(LibroRequest $request)
    {
        $id_libro = $request->integer('id_libro');
        $body = $request->validated();

        if ($request->hasFile('foto')) {
            $body['foto'] = $request->file('foto');
        }

        $response = $this->biblioteca_services->editarLibro($body, $id_libro);

        return $this->apiResponse($response);
    }

    public function cambiarEstadoLibro(Request $request)
    {
        $ids_libros = $request->input('ids');
        $estado = $request->input('estado', 0);

        if (is_string($estado)) {
            $estado = $estado === 'activo' ? 1 : ($estado === 'inactivo' ? 0 : (int) $estado);
        }

        $response = $this->biblioteca_services->cambiarEstadoLibro(
            $ids_libros,
            (int) $estado
        );

        return $this->apiResponse($response);
    }

    public function estadisticasEjemplaresBiblioteca(Request $request)
    {
        $response = $this->biblioteca_services->estadisticasEjemplaresBiblioteca(
            $request->integer('id_libro') ?: null
        );
        return $this->apiResponse($response);
    }

    public function agregarEjemplarLibroBiblioteca(EjemplaresRequest $request)
    {
        $body = $request->validated();

        $cantidad = $body['cantidad'];

        unset($body['cantidad']);

        $response = $this->biblioteca_services->agregarEjemplarLibroBiblioteca($body, $cantidad);

        return $this->apiResponse($response);
    }

    public function verEjemplaresLibroBiblioteca(Request $request)
    {
        $response = $this->biblioteca_services->verEjemplaresLibroBiblioteca(
            $request->integer("id_libro") ?: null,
            $request->input("autor"),
            $request->integer("perpage") ?: null
        );

        return $this->apiResponse($response);
    }

    public function verEjemplaresDeshabilitadosLibroBiblioteca(Request $request)
    {
        $response = $this->biblioteca_services->verEjemplaresDeshabilitadosLibroBiblioteca(
            $request->integer("id_libro") ?: null,
            $request->integer("perpage") ?: null
        );

        return $this->apiResponse($response);
    }

    public function cambiarEstadoEjemplarBiblioteca(Request $request)
    {
        $response = $this->biblioteca_services->cambiarEstadoEjemplarBiblioteca(
            $request->input('ids_ejemplares'),
            $request->input('estado', 4)
        );

        return $this->apiResponse($response);
    }

    public function verPrestamosDeEjemplar(Request $request)
    {
        $id_ejemplar = $request->input("id_ejemplar");

        $response = $this->biblioteca_services->verPrestamosDeEjemplar($id_ejemplar);
        return $this->apiResponse($response);
    }

    public function obtenerHistorialPrestamoEjemplarUsuario(Request $request){
        $id_usuario = $request->input('id_usuario');
        $deuda = $request->input('deuda', false);

        $response = $this->biblioteca_services->obtenerHistorialPrestamoEjemplarUsuario($id_usuario, $deuda);

        return $this->apiResponse($response);
    }

    public function verPrestamosLibro(Request $request)
    {
        $id_libro = $request->input("id_libro");

        $response = $this->biblioteca_services->verPrestamosLibro($id_libro);
        return $this->apiResponse($response);
    }

    public function prestarEjemplarBiblioteca(PrestamoEjemplarRequest $request)
    {
        $body = $request->validated();

        $response = $this->biblioteca_services->prestarEjemplarBiblioteca($body);

        return $this->apiResponse($response);
    }

    public function devolverPrestamoEjemplarBiblioteca(Request $request)
    {
        $body = [
            "codigo_ejemplar" => $request->input("codigo_ejemplar"),
            "id_log" => $request->input("id_log")
        ];

        $response = $this->biblioteca_services->devolverPrestamoEjemplarBiblioteca($body);

        return $this->apiResponse($response);
    }

    public function listarPaquetesBiblioteca(Request $request)
    {
        $body = $request->input("search");

        $response = $this->biblioteca_services->listarPaquetesBiblioteca($body);

        return $this->paginatedResponse($response);
    }

    public function crearNuevoPaqueteBiblioteca(PaqueteRequest $request)
    {
        $body = $request->validated();

        $response = $this->biblioteca_services->crearNuevoPaqueteBiblioteca($body);

        return $this->apiResponse($response);
    }

    public function cambiarEstadoPaqueteBiblioteca(Request $request){
        $ids_paquetes = $request->input('ids');
        $estado = $request->input('estado', 0);

        $response = $this->biblioteca_services->cambiarEstadoPaqueteBiblioteca($ids_paquetes, $estado);
        return $this->apiResponse($response);
    }

    public function agregarContenidoPaqueteBiblioteca(ContenidoPaqueteRequest $request){
        $body = $request->validated();

        $response = $this->biblioteca_services->agregarContenidoPaqueteBiblioteca($body);

        return $this->apiResponse($response);
    }

    public function cambiarEstadoContenidoPaqueteBiblioteca(Request $request){
        $ids = $request->input('ids');
        $estado = $request->input('estado', 0);
        $id_paquete = $request->input('id_paquete');

        $response = $this->biblioteca_services->cambiarEstadoContenidoPaqueteBiblioteca($ids, $id_paquete, $estado);
        
        return $this->apiResponse($response);
    }

    public function editarDatosContenidoPaqueteBiblioteca(ContenidoPaqueteRequest $request){
        $body = $request->validated();

        $response = $this->biblioteca_services->editarDatosContenidoPaqueteBiblioteca($body, $request->input('id_contenido'));

        return $this->apiResponse($response);
    }

    public function mostrarHistorialPrestamosPaquetesUsuario(Request $request){
        $id_usuario = $request->input('id_usuario');
        $deuda = $request->input('deuda', false);

        $response = $this->biblioteca_services->mostrarHistorialPrestamosPaquetesUsuario($id_usuario, $deuda);

        return $this->apiResponse($response);
    }

    public function generarPrestamoPaqueteUsuario(Request $request){
        $id_usuario = $request->input('id_usuario');
        $id_paquete = $request->input('id_paquete');

        #Generamos una fecha aleatoria de ahora + 1 mes
        $fecha = date('Y-m-d', strtotime('+1 month'));

        $fecha_devolucion = $request->input('fecha_devolucion', $fecha);
        $observacion = $request->input('observacion');

        $response = $this->biblioteca_services->generarPrestamoPaqueteUsuario($id_usuario, $id_paquete, $fecha_devolucion, $observacion);

        return $this->apiResponse($response);
    }

    public function devolverPrestamoPaqueteUsuario(Request $request){
        $id_prestamo = $request->input('id_prestamo');
        $observacion = $request->input('observacion');

        $response = $this->biblioteca_services->devolverPrestamoPaqueteUsuario($id_prestamo, $observacion);

        return $this->apiResponse($response);
    }
}

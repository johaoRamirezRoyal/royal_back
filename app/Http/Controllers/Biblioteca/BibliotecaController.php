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

    public function mostrarSubcategoriasBiblioteca()
    {

        $response = $this->biblioteca_services->mostrarSubcategoriasBiblioteca();

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
                $request->input('search'),
                $request->input('categoria'),
                $request->input('subcategoria'),
                $request->input('activo', 1),
                $request->input("perpage", 30),
            );

        return $this->apiResponse($response);
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
        $id_libro = $request->input('id_libro');
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

        $response = $this->biblioteca_services->cambiarEstadoLibro(
            $ids_libros,
            $request->input('estado', 0)
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
            $request->input("id_libro"),
            $request->input("autor"),
            $request->input("perpage")
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
}

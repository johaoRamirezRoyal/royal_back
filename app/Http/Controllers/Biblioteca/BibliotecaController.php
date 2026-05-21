<?php
namespace App\Http\Controllers\Biblioteca;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biblioteca\CategoriaRequest;
use App\Http\Requests\Biblioteca\LibroRequest;
use App\Http\Requests\Biblioteca\SubcategoriaRequest;
use App\Services\Biblioteca\BibliotecaServices;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

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
    public function mostrarCategoriasBiblioteca(){

        $response = $this->biblioteca_services->mostrarCategoriasBiblioteca();

        return $this->apiResponse($response);
    }

    public function cambiarEstadoCategoriaBiblioteca(Request $request){
        $id_categoria = $request->input("id_categoria");
        $estado = $request->input("estado");

        $response = $this->biblioteca_services->cambiarEstadoCategoriaBiblioteca($id_categoria, $estado);

        return $this->apiResponse($response);
    }

    public function mostrarSubcategoriasBiblioteca(){

        $response = $this->biblioteca_services->mostrarSubcategoriasBiblioteca();

        return $this->apiResponse($response);
    }

    public function agregarCategoriaBiblioteca(CategoriaRequest $request){
        $r = $request->validated();

        $response = $this->biblioteca_services->agregarCategoriaBiblioteca($r);

        return $this->apiResponse($response);
    }

    public function cambiarEstadoSubcategoriaBiblioteca(Request $request){
        $id_subcategoria = $request->input("id_subcategoria");
        $estado = $request->input("estado");

        $response = $this->biblioteca_services->cambiarEstadoSubcategoriaBiblioteca($id_subcategoria, $estado);
        return $this->apiResponse($response);
    }

    public function agregarSubcategoriaBiblioteca(SubcategoriaRequest $request){
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

    public function agregarNuevoLibroBiblioteca(LibroRequest $request){
        $body = $request->validated();

        if ($request->hasFile('imagen')) {

            $archivo = $this->file_storage_service->guardar(
                $request->file('imagen'),
                'biblioteca'
            );

            $body['imagen'] = $archivo['ruta'];
        }

        $response = $this->biblioteca_services->agregarNuevoLibroBiblioteca($body);

        return $this->apiResponse($response);
    }
}
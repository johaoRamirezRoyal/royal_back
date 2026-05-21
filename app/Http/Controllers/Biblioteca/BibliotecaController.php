<?php
namespace App\Http\Controllers\Biblioteca;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biblioteca\CategoriaRequest;
use App\Http\Requests\Biblioteca\SubcategoriaRequest;
use App\Services\Biblioteca\BibliotecaServices;
use Illuminate\Http\Request;

class BibliotecaController extends Controller

{
    protected BibliotecaServices $biblioteca_services;

    public function __construct(BibliotecaServices $bibliotecaServices)
    {
        $this->biblioteca_services = $bibliotecaServices;
    }

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
}
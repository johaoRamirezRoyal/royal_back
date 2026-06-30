<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionAcademica\AsignaturaRequest;
use App\Http\Requests\GestionAcademica\DocenteAsignaturaRequest;
use App\Services\GestionAcademica\GestionAcademicaService;
use Illuminate\Http\Request;

class GestionAcademicaController extends Controller
{
    public function __construct(
        private GestionAcademicaService $service
    ) {}

    public function listarAsignaturas(Request $request)
    {
        $nombre = $request->input('nombre', null);
        $codigo = $request->input('codigo', null);
        $abreviatura = $request->input('abreviatura', null);
        $estado = $request->input('estado', null);

        $response = $this->service->asignatura()->mostrarAsignaturasFiltradas($nombre, $codigo, $abreviatura, $estado);

        return $this->apiResponse($response);
    }

    public function obtenerAsignatura(int $id)
    {
        return $this->apiResponse($this->service->asignatura()->obtenerPorId($id));
    }

    public function crearAsignatura(AsignaturaRequest $request)
    {
        $body = $request->validated();

        $response = $this->service->asignatura()->crear($body);

        return $this->apiResponse($response);
    }

    public function actualizarAsignatura(AsignaturaRequest $request)
    {
        $body = $request->validated();
        $id = $request->input('id');

        $response = $this->service->asignatura()->actualizar($id, $body);

        return $this->apiResponse($response);
    }

    public function eliminarAsignatura(Request $request)
    {
        $id = $request->input('id');
        return $this->apiResponse($this->service->asignatura()->eliminar($id));
    }

    public function listarDocentesAsignaturas(Request $request)
    {
        $usuario = $request->input('usuario', null);
        $asignatura = $request->input('asignatura', null);
        $search = $request->input('s', null);
        $perpage = $request->input('per-page', 10);

        $response = $this->service->docenteAsignatura()->listarDocentesAsignaturas($usuario, $asignatura, $search, $perpage);

        return $this->paginatedResponse($response);
    }

    public function asignarAsignaturasDocente(DocenteAsignaturaRequest $request)
    {
        $body = $request->validated();

        $response = $this->service->docenteAsignatura()->asignarAsignaturasDocente($body['id_user'], $body['asignaturas']);

        return $this->apiResponse($response);
    }

    public function eliminarAsignaturasDocente(Request $request){
        $ids = $request->input('ids');

        $response = $this->service->docenteAsignatura()->eliminarAsignaturasDocente($ids);

        return $this->apiResponse($response);
    }
}

<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enfermeria\EnfermeriaAtencionRequest;
use App\Http\Requests\Enfermeria\EnfermeriaCategoriaRequest;
use App\Services\Enfermeria\EnfermeriaServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnfermeriaController extends Controller
{
    protected EnfermeriaServices $enfermeriaServices;

    public function __construct(EnfermeriaServices $enfermeriaServices)
    {
        $this->enfermeriaServices = $enfermeriaServices;
    }

    /*
    -------------------------------------------------
    |
    |             CATEGORÍAS
    |
    -------------------------------------------------
    */

    /**
     * GET /api/enfermeria/categorias
     */
    public function obtenerCategorias(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerCategorias(
            $request->input('search'),
            $request->input('per-page', 15)
        );

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/activas
     */
    public function obtenerCategoriasActivas(): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerCategoriasActivas();

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/select
     */
    public function selectCategorias(): JsonResponse
    {
        $response = $this->enfermeriaServices->selectCategorias();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/conteo
     */
    public function obtenerCategoriasConConteo(): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerCategoriasConConteo();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/estadisticas
     */
    public function estadisticas(): JsonResponse
    {
        $response = $this->enfermeriaServices->estadisticas();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/{id}
     */
    public function obtenerCategoriaPorId(int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerCategoriaPorId($id);

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/verificar-nombre?nombre=...&id=...
     */
    public function verificarNombre(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->nombreExiste(
            $request->input('nombre'),
            $request->input('id')
        );

        return $this->apiResponse($response);
    }

    /**
     * POST /api/enfermeria/categorias
     */
    public function crearCategoria(EnfermeriaCategoriaRequest $request): JsonResponse
    {
        $response = $this->enfermeriaServices->crearCategoria($request->validated());

        return $this->apiResponse($response);
    }

    /**
     * PUT /api/enfermeria/categorias/{id}
     */
    public function actualizarCategoria(EnfermeriaCategoriaRequest $request, int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->actualizarCategoria($request->validated(), $id);

        return $this->apiResponse($response);
    }

    /**
     * PUT /api/enfermeria/categorias/estado
     */
    public function cambiarEstadoCategoria(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->cambiarEstadoCategoria(
            $request->input('id'),
            $request->input('estado')
        );

        return $this->apiResponse($response);
    }

    /**
     * PUT /api/enfermeria/categorias/estado-masivo
     */
    public function cambiarEstadoMasivo(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->cambiarEstadoMasivo(
            $request->input('ids'),
            $request->input('estado')
        );

        return $this->apiResponse($response);
    }

    /**
     * DELETE /api/enfermeria/categorias/{id}
     */
    public function eliminarCategoria(int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->eliminarCategoria($id);

        return $this->apiResponse($response);
    }

    /*
    -------------------------------------------------
    |
    |             ATENCIONES
    |
    -------------------------------------------------
    */

    /**
     * GET /api/enfermeria/atenciones
     */
    public function obtenerAtenciones(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerAtenciones(
            $request->input('search'),
            $request->input('id_estudiante'),
            $request->input('id_categoria'),
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('per-page', 15)
        );

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/atenciones/{id}
     */
    public function obtenerAtencionPorId(int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->obtenerAtencionPorId($id);

        return $this->apiResponse($response);
    }

    /**
     * POST /api/enfermeria/atenciones
     */
    public function crearAtencion(EnfermeriaAtencionRequest $request): JsonResponse
    {
        $response = $this->enfermeriaServices->crearAtencion($request->validated());

        return $this->apiResponse($response);
    }

    /**
     * POST /api/enfermeria/atenciones/{id}/reenviar-correo
     */
    public function reenviarCorreo(int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->reenviarCorreo($id);

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/atenciones/estadisticas
     */
    public function estadisticasAtenciones(): JsonResponse
    {
        $response = $this->enfermeriaServices->estadisticasAtenciones();

        return $this->apiResponse($response);
    }

    /**
     * DELETE /api/enfermeria/atenciones/{id}
     */
    public function eliminarAtencion(int $id): JsonResponse
    {
        $response = $this->enfermeriaServices->eliminarAtencion($id);

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/estadisticas/cursos
     */
    public function cursosConMasAtenciones(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->cursosConMasAtenciones(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('limite', 10)
        );

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/estadisticas/estudiantes
     */
    public function estudiantesConMasAtenciones(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->estudiantesConMasAtenciones(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('limite', 10)
        );

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/estadisticas/categorias
     */
    public function categoriasMasRegistradas(Request $request): JsonResponse
    {
        $response = $this->enfermeriaServices->categoriasMasRegistradas(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('limite', 10)
        );

        return $this->apiResponse($response);
    }
}

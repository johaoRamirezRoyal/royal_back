<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enfermeria\EnfermeriaAtencionRequest;
use App\Http\Requests\Enfermeria\EnfermeriaCategoriaRequest;
use App\Services\Enfermeria\EnfermeriaServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnfermeriaController extends Controller
{
    // Opciones del frontend (ver CLAUDE.md "Enfermería"): 54 = /enfermeria/categorias,
    // 57 = /enfermeria/atencion-medica + /listado + /historial (comparten permiso),
    // 64 = /enfermeria/metricas. Este controller respalda las tres, así que a diferencia
    // de GestionAcademicaController no alcanza un único chequeo en el constructor — cada
    // grupo de endpoints gatea la opción que le corresponde.
    private const OPCION_CATEGORIAS = 54;
    private const OPCION_ATENCIONES = 57;
    private const OPCION_METRICAS = 64;

    protected EnfermeriaServices $enfermeriaServices;

    public function __construct(
        EnfermeriaServices $enfermeriaServices,
        private UsuariosServices $usuariosService,
    ) {
        $this->enfermeriaServices = $enfermeriaServices;
    }

    /**
     * Chequeo server-side del permiso, no solo ocultar la ruta en el frontend — cualquier
     * intento directo a estos endpoints sin ninguna de las opciones dadas se rechaza acá.
     * Acepta varias opciones (OR) para los endpoints que comparten más de una, ej.
     * `selectCategorias` (lo usa tanto Categorías como el modal de registrar atención).
     */
    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        foreach ($opciones as $opcion) {
            if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->obtenerCategorias(
            $request->input('search'),
            $request->input('per-page', 15)
        );

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/activas
     */
    public function obtenerCategoriasActivas(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $perPage = $request->input('per_page');
        $response = $this->enfermeriaServices->obtenerCategoriasActivas(
            $perPage ? (int) $perPage : null
        );

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/select
     * Compartido con Atención médica (dropdown de categoría al registrar una atención).
     */
    public function selectCategorias(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->selectCategorias();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/conteo
     */
    public function obtenerCategoriasConConteo(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->obtenerCategoriasConConteo();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/estadisticas
     */
    public function estadisticas(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->estadisticas();

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/{id}
     */
    public function obtenerCategoriaPorId(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->obtenerCategoriaPorId($id);

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/categorias/verificar-nombre?nombre=...&id=...
     */
    public function verificarNombre(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->crearCategoria($request->validated());

        return $this->apiResponse($response);
    }

    /**
     * PUT /api/enfermeria/categorias/{id}
     */
    public function actualizarCategoria(EnfermeriaCategoriaRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->actualizarCategoria($request->validated(), $id);

        return $this->apiResponse($response);
    }

    /**
     * PUT /api/enfermeria/categorias/estado
     */
    public function cambiarEstadoCategoria(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->cambiarEstadoMasivo(
            $request->input('ids'),
            $request->input('estado')
        );

        return $this->apiResponse($response);
    }

    /**
     * DELETE /api/enfermeria/categorias/{id}
     */
    public function eliminarCategoria(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CATEGORIAS)) {
            return $rechazo;
        }

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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->obtenerAtenciones(
            $request->input('s') ?? $request->input('search'),
            $request->input('id_estudiante'),
            $request->input('id_categoria'),
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('per-page', 15),
            $request->input('sort'),
            $request->input('dir', 'desc')
        );

        return $this->paginatedResponse($response);
    }

    /**
     * GET /api/enfermeria/atenciones/{id}
     */
    public function obtenerAtencionPorId(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->obtenerAtencionPorId($id);

        return $this->apiResponse($response);
    }

    /**
     * POST /api/enfermeria/atenciones
     */
    public function crearAtencion(EnfermeriaAtencionRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->crearAtencion($request->validated());

        return $this->apiResponse($response);
    }

    /**
     * POST /api/enfermeria/atenciones/{id}/reenviar-correo
     */
    public function reenviarCorreo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->reenviarCorreo($id);

        return $this->apiResponse($response);
    }

    /**
     * GET /api/enfermeria/atenciones/estadisticas
     */
    public function estadisticasAtenciones(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->estadisticasAtenciones();

        return $this->apiResponse($response);
    }

    /**
     * DELETE /api/enfermeria/atenciones/{id}
     */
    public function eliminarAtencion(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ATENCIONES)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->eliminarAtencion($id);

        return $this->apiResponse($response);
    }

    /*
    -------------------------------------------------
    |
    |             ESTADÍSTICAS (Métricas)
    |
    -------------------------------------------------
    */

    /**
     * GET /api/enfermeria/estadisticas/cursos
     */
    public function cursosConMasAtenciones(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_METRICAS)) {
            return $rechazo;
        }

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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_METRICAS)) {
            return $rechazo;
        }

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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_METRICAS)) {
            return $rechazo;
        }

        $response = $this->enfermeriaServices->categoriasMasRegistradas(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('limite', 10)
        );

        return $this->apiResponse($response);
    }
}

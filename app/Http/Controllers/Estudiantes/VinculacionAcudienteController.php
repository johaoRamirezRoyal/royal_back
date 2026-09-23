<?php

namespace App\Http\Controllers\Estudiantes;

use App\Http\Controllers\Controller;
use App\Services\Estudiantes\VinculacionAcudienteService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Módulo "Vinculación Estudiante - Padre" (Académico): listar acudientes (perfil 6),
 * buscar estudiantes (perfil 16) y vincular/desvincular en `estudiantes_padres`.
 */
class VinculacionAcudienteController extends Controller
{
    // Id real confirmado contra cron_opciones tras correr
    // 2026_09_23_100000_seed_opcion_vinculacion_estudiante_padre (insertGetId — no
    // asumir el literal en otra BD, ver drift documentado en AGENTS.md).
    private const OPCION_VINCULACION = 131;

    private const PERFIL_ESTUDIANTE = 16;

    public function __construct(
        private UsuariosServices $usuariosService,
        private VinculacionAcudienteService $vinculacionService,
    ) {
    }

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        if ($this->usuariosService->tienePermiso(self::OPCION_VINCULACION, $perfil)['permiso'] ?? false) {
            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    public function index(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->usuariosService->mostrarAcudientesPaginados(
            (int) $request->input('per-page', 10),
            $request->input('s'),
            $request->input('estado'),
            $request->input('sort', 'nombre'),
            $request->input('dir', 'asc'),
        );

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    /** Búsqueda paginada de estudiantes activos por nombre/documento y curso(s). */
    public function buscarEstudiantes(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->usuariosService->mostrarUsuariosPaginados(
            (int) $request->input('per-page', 10),
            [self::PERFIL_ESTUDIANTE],
            null,
            $request->input('s'),
            'activo',
            'nombre',
            'asc',
            $request->input('cursos'),
        );

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    public function estudiantes(Request $request, int $idAcudiente)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->vinculacionService->estudiantesVinculados($idAcudiente);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->success($response['message'], $response['data']);
    }

    public function vincular(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $data = $request->validate([
            'id_acudiente' => 'required|integer',
            'id_estudiante' => 'required|integer',
        ]);

        $response = $this->vinculacionService->vincular($data['id_acudiente'], $data['id_estudiante']);

        if ($response['error']) {
            return $this->error($response['message'], 422);
        }

        return $this->success($response['message'], $response['data']);
    }

    /** Vinculación masiva: multipart `archivo` (.xlsx/.xls/.csv) — ver VinculacionAcudienteService::importarExcel. */
    public function importar(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $response = $this->vinculacionService->importarExcel($request->file('archivo')->getRealPath());

        if ($response['error']) {
            return $this->error($response['message'], 422);
        }

        return $this->success($response['message'], $response['data']);
    }

    public function desvincular(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $data = $request->validate([
            'id_acudiente' => 'required|integer',
            'id_estudiante' => 'required|integer',
        ]);

        $response = $this->vinculacionService->desvincular($data['id_acudiente'], $data['id_estudiante']);

        if ($response['error']) {
            return $this->error($response['message'], 422);
        }

        return $this->success($response['message']);
    }
}

<?php

namespace App\Http\Controllers\Estudiantes;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Http\Controllers\Controller;
use App\Models\Areas\Cursos;
use App\Models\Usuarios\Usuario;
use App\Services\Estudiantes\VinculacionAcudienteService;
use Illuminate\Support\Arr;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Permiso;
use App\Http\Controllers\Concerns\VerificaPermisos;

/**
 * Módulo "Vinculación Estudiante - Padre" (Académico): listar acudientes (perfil 6),
 * buscar estudiantes (perfil 16) y vincular/desvincular en `estudiantes_padres`.
 */
class VinculacionAcudienteController extends Controller
{
    use VerificaPermisos;

    // Id real confirmado contra cron_opciones tras correr
    // 2026_09_23_100000_seed_opcion_vinculacion_estudiante_padre (insertGetId — no
    // asumir el literal en otra BD, ver drift documentado en AGENTS.md).
    private const OPCION_VINCULACION = Permiso::VINCULACION_ACUDIENTES;


    public function __construct(
        private UsuariosServices $usuariosService,
        private VinculacionAcudienteService $vinculacionService,
    ) {
    }

    public function index(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
            return $rechazo;
        }

        $response = $this->usuariosService->mostrarUsuariosPaginados(
            (int) $request->input('per-page', 10),
            Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE),
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
            return $rechazo;
        }

        $response = $this->vinculacionService->estudiantesVinculados($idAcudiente);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->success($response['message'], $response['data']);
    }

    /**
     * Con la 131, los acudientes completos. Sin ella, solo el director de grupo del curso en el
     * que HOY está el estudiante, y solo el contacto (sin documento del acudiente).
     */
    public function acudientes(Request $request, int $idEstudiante)
    {
        if (! $this->sinAcceso($request, self::OPCION_VINCULACION)) {
            return $this->success('Datos obtenidos satisfactoriamente', $this->vinculacionService->acudientesVinculados($idEstudiante));
        }

        $esSuDirector = Usuario::where('id_user', $idEstudiante)
            ->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE))
            ->whereIn('id_curso', Cursos::where('id_director', $request->user()->id_user)->where('activo', 1)->select('id'))
            ->exists();

        if (! $esSuDirector) {
            return $this->error('No tienes permiso para esta acción', 403);
        }

        $contacto = collect($this->vinculacionService->acudientesVinculados($idEstudiante))
            ->map(fn ($a) => Arr::only($a->toArray(), ['id_user', 'nombre', 'apellido', 'correo', 'telefono']))
            ->values();

        return $this->success('Datos obtenidos satisfactoriamente', $contacto);
    }

    public function vincular(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VINCULACION)) {
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

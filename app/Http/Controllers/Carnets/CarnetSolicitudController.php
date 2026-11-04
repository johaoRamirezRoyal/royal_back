<?php

namespace App\Http\Controllers\Carnets;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Services\Carnets\CarnetService;
use App\Services\Cursos\CursosServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solicitudes de carnet. Pedir (opción 147): el propio carnet, o el de un estudiante del curso
 * que dirige. Ver y responder (opción 144): quien administra Carnets.
 */
class CarnetSolicitudController extends Controller
{
    use VerificaPermisos;

    public function __construct(
        private CarnetService $carnets,
        private CursosServices $cursos,
    ) {}

    /** POST /api/carnets/solicitudes { tipo, observacion?, id_user? } — sin id_user, la propia. */
    public function store(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS_SOLICITAR)) {
            return $rechazo;
        }

        $data = $request->validate([
            'tipo' => ['required', Rule::in(CarnetService::TIPOS_SOLICITUD)],
            'observacion' => ['nullable', 'string', 'max:255'],
            'id_user' => ['nullable', 'integer'],
        ], [
            'tipo.required' => 'Elige el tipo de solicitud',
            'tipo.in' => 'El tipo de solicitud no es válido',
        ]);

        $yo = $request->user()->id_user;
        $para = (int) ($data['id_user'] ?? $yo);

        // Para otro: solo el director de grupo, y solo para un estudiante de su curso.
        if ($para !== $yo && ! $this->cursos->estudiantesDeMiCurso($yo, [$para])) {
            return $this->error('Solo puedes pedir tu carnet o el de un estudiante del curso que diriges', 403);
        }

        return $this->apiResponse($this->carnets->solicitar($para, $data['tipo'], $data['observacion'] ?? null, $yo));
    }

    /** GET /api/carnets/solicitudes/mias — seguimiento de las solicitudes del carnet propio. */
    public function mias(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS_SOLICITAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->carnets->seguimiento([$request->user()->id_user]));
    }

    /** GET /api/carnets/solicitudes/mi-curso — seguimiento de las de los estudiantes del curso que dirige. */
    public function miCurso(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS_SOLICITAR)) {
            return $rechazo;
        }

        $estudiantes = $this->cursos->estudiantesDeMiCurso($request->user()->id_user, null);

        return $this->apiResponse($estudiantes ? $this->carnets->seguimiento($estudiantes) : ['error' => false, 'message' => 'No diriges ningún curso', 'data' => []]);
    }

    /** GET /api/carnets/solicitudes?estado=PENDIENTE|ATENDIDA|RECHAZADA&page=&per-page= (144). */
    public function index(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS)) {
            return $rechazo;
        }

        $estado = in_array($request->input('estado'), ['PENDIENTE', 'ATENDIDA', 'RECHAZADA'], true) ? $request->input('estado') : null;

        return $this->paginatedResponse($this->carnets->solicitudes($estado, (int) $request->input('per-page', 20)));
    }

    /** PUT /api/carnets/solicitudes/{id} { estado: ATENDIDA|RECHAZADA, respuesta? } (144). */
    public function responder(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS)) {
            return $rechazo;
        }

        $data = $request->validate([
            'estado' => ['required', Rule::in(['ATENDIDA', 'RECHAZADA'])],
            'respuesta' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->apiResponse($this->carnets->responderSolicitud($id, $data['estado'], $data['respuesta'] ?? null, $request->user()->id_user));
    }
}

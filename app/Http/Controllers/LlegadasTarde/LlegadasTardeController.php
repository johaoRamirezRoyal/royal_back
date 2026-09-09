<?php
namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Http\Requests\LlegadasTarde\LlegadaTardeRequest;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\LlegadasTarde\LlegadasTarde as LlegadaTardeModel;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;

class LlegadasTardeController extends Controller
{
    // Opción "Gestión Académica" (/permisos): quien la tiene ve/gestiona el módulo
    // completo (cualquier fecha, eliminar, dashboard). Sin ella pero con acceso al
    // módulo (opción 101, ver migración 2026_08_18_100000) el acceso es restringido:
    // solo hoy, sin eliminar ni dashboard. Para dar ese mismo acceso restringido a un
    // perfil nuevo alcanza con otorgarle la opción 101 desde /permisos, sin tocar código.
    private const OPCION_ACCESO_COMPLETO = 99;

    // Autoservicio (sin pasar por /permisos, igual que el Docente en Gestión Académica —
    // ver GestionAcademicaController::METODOS_DOCENTE): un Acudiente siempre ve el
    // historial completo (todos los períodos, no solo hoy) de sus propios hijos
    // ACTIVOS en `estudiantes_padres`, nunca de otro estudiante — ver obtenerLlegadasTarde.
    private const PERFIL_ACUDIENTE = 6;

    public function __construct(
        private LlegadasTarde $llegadas_tarde,
        private UsuariosServices $usuariosService,
    ) {}

    private function tieneAccesoCompleto(Request $request): bool
    {
        return $this->usuariosService->tienePermiso(self::OPCION_ACCESO_COMPLETO, $request->user()->perfil)['permiso'] ?? false;
    }

    /** IDs de los estudiantes vinculados (activo=1) al Acudiente autenticado — ver
     * EstudiantesPadre. Nunca confiar en un id_alumno que mande el cliente para este
     * perfil, siempre se resuelve server-side. */
    private function idsHijosDe(Request $request): array
    {
        return EstudiantesPadre::where('id_acudiente', $request->user()->id_user)
            ->where('activo', 1)
            ->pluck('id_estudiante')
            ->all();
    }

    /** Ambas acciones "operativas" (reenviar correo, editar observación) no exigen la
     * opción 99/101 — pero para un Acudiente sí hay que verificar que la llegada tarde
     * puntual sea de uno de sus hijos, o cualquier Acudiente podría tocar el registro de
     * un estudiante ajeno solo conociendo su ID. Perfiles no-Acudiente no se tocan (esos
     * ya están cubiertos por tener acceso al módulo en primer lugar). */
    private function bloqueadoParaAcudiente(Request $request, int $id): bool
    {
        if ($request->user()->perfil !== self::PERFIL_ACUDIENTE) {
            return false;
        }

        $idAlumno = LlegadaTardeModel::where('id', $id)->value('id_alumno');

        return $idAlumno === null || !in_array($idAlumno, $this->idsHijosDe($request), true);
    }

    public function agregarLlegadaTarde(LlegadaTardeRequest $request){
        $body = $request->validated();

        $id_alumno = $body['id_alumno'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];
        $justificada = $body['justificada'] ?? false;
        $observacion = $body['observacion'] ?? null;
        $soporte = $request->file('soporte');

        $response = $this->llegadas_tarde->agregarLlegadaTarde($id_alumno, $fecha, $hora, $justificada, $observacion, $soporte);

        return $this->apiResponse($response);
    }

    public function obtenerLlegadasTarde(Request $request){

        $id_anio_academico = $request->input('id_periodo_academico', null);

        $esAcudiente = $request->user()->perfil === self::PERFIL_ACUDIENTE;

        // Autoservicio del Acudiente: ignora cualquier id_alumno/fecha que mande el
        // cliente — siempre se resuelve a SUS hijos, historial completo (no solo hoy,
        // a diferencia del acceso restringido por opción 101 más abajo).
        if ($esAcudiente) {
            $idsHijos = $this->idsHijosDe($request);

            $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, null, null, $idsHijos);

            return $this->apiResponse($response);
        }

        $id_alumno = $request->input('id_alumno', null);

        // Sin acceso completo, solo se pueden ver las llegadas tarde del día actual: se
        // ignora cualquier `fecha` que mande el cliente y se fuerza hoy.
        $fecha = $this->tieneAccesoCompleto($request)
            ? $request->input('fecha', null)
            : now()->toDateString();

        $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, $id_alumno, $fecha);

        return $this->apiResponse($response);

    }

    public function dashboardLlegadasTarde(Request $request){
        if (!$this->tieneAccesoCompleto($request)) {
            return $this->error('No tienes permiso para ver el dashboard de llegadas tarde', 403);
        }

        $id_periodo_academico = $request->input('id_periodo_academico', null);

        $response = $this->llegadas_tarde->dashboardLlegadasTarde($id_periodo_academico);

        return $this->apiResponse($response);
    }

    public function eliminarLlegadaTarde(Request $request, ?int $id = null){
        if (!$this->tieneAccesoCompleto($request)) {
            return $this->error('No tienes permiso para eliminar llegadas tarde', 403);
        }

        $ids = $id !== null ? [$id] : $request->input('ids_llegadas_tarde', []);

        $response = $this->llegadas_tarde->eliminarLlegadaTarde($ids);

        return $this->apiResponse($response);
    }

    // Reenviar correo es una acción operativa (como registrar la llegada tarde), no
    // administrativa: no requiere acceso completo, cualquiera con acceso al módulo
    // (99 o 101) puede reintentar la notificación.
    public function reenviarCorreo(Request $request, int $id){
        if ($this->bloqueadoParaAcudiente($request, $id)) {
            return $this->error('No tienes permiso sobre esta llegada tarde', 403);
        }

        $response = $this->llegadas_tarde->reenviarCorreo($id);

        return $this->apiResponse($response);
    }

    // Anotar una observación es operativa, igual que reenviar correo: no requiere
    // acceso completo.
    public function actualizarObservacion(Request $request, int $id){
        if ($this->bloqueadoParaAcudiente($request, $id)) {
            return $this->error('No tienes permiso sobre esta llegada tarde', 403);
        }

        $observacion = $request->validate([
            'observacion' => 'nullable|string|max:1000',
        ])['observacion'] ?? null;

        $response = $this->llegadas_tarde->actualizarObservacion($id, $observacion);

        return $this->apiResponse($response);
    }

    // Revocar, como eliminar, cambia el conteo del alumno frente al límite: requiere
    // acceso completo.
    public function revocarLlegadaTarde(Request $request, int $id){
        if (!$this->tieneAccesoCompleto($request)) {
            return $this->error('No tienes permiso para revocar llegadas tarde', 403);
        }

        $observacion = $request->validate([
            'observacion' => 'nullable|string|max:1000',
        ])['observacion'] ?? null;

        $response = $this->llegadas_tarde->revocarLlegadaTarde($id, $observacion);

        return $this->apiResponse($response);
    }
}

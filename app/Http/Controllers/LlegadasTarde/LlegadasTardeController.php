<?php
namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Http\Requests\LlegadasTarde\LlegadaTardeRequest;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\LlegadasTarde\LlegadasTarde as LlegadaTardeModel;
use App\Models\Usuarios\Usuario;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LlegadasTardeController extends Controller
{
    // Opción "Gestión Académica" (/permisos): quien la tiene ve/gestiona el módulo
    // completo (cualquier fecha, eliminar, dashboard). Sin ella pero con acceso al
    // módulo (opción 101, ver migración 2026_08_18_100000) el acceso es restringido:
    // solo hoy, sin eliminar ni dashboard. Para dar ese mismo acceso restringido a un
    // perfil nuevo alcanza con otorgarle la opción 101 desde /permisos, sin tocar código.
    private const OPCION_ACCESO_COMPLETO = 99;

    // Autoservicio (sin pasar por /permisos, igual que el Docente en Gestión Académica —
    // ver GestionAcademicaController::METODOS_DOCENTE): un Acudiente ve sus propios hijos
    // ACTIVOS en `estudiantes_padres`, nunca de otro estudiante — ver obtenerLlegadasTarde.
    private const PERFIL_ACUDIENTE = 6;

    private const PERFIL_ESTUDIANTE = 16;

    // Autoservicio de un Docente SIN la opción 99 (Gestión Académica completa): ve las
    // llegadas tarde de los alumnos de SUS cursos (carga académica activa), nunca de un
    // curso ajeno — mismo criterio de "resolver los cursos propios" que ya usa
    // AsistenciaEstudianteService::metricasPorCurso ($id_docente_scope). Un Docente CON
    // la opción 99 (ej. también Coordinador) cae en la rama general de abajo, sin scope.
    private const PERFIL_DOCENTE = 3;

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

    /** IDs de los estudiantes activos matriculados en los cursos donde el Docente
     * autenticado tiene carga académica activa — mismo join que
     * AsistenciaEstudianteService::metricasPorCurso usa para resolver "sus cursos".
     * Vacío (ningún curso asignado todavía) es un resultado válido, no un error. */
    private function idsAlumnosDeCursosDocente(Request $request): array
    {
        $cursos = DB::table('academico_carga_academica as ca')
            ->join('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
            ->where('da.id_docente', $request->user()->id_user)
            ->where('ca.activo', 1)
            ->pluck('ca.id_curso')
            ->unique();

        if ($cursos->isEmpty()) {
            return [];
        }

        return Usuario::where('perfil', self::PERFIL_ESTUDIANTE)
            ->where('estado', 'activo')
            ->whereIn('id_curso', $cursos)
            ->pluck('id_user')
            ->all();
    }

    /** "Operativas" (reenviar correo, editar observación) no exigen la opción 99/101 — el
     * Acudiente sí puede hacerlas sobre sus propios hijos (por eso valida scope en vez de
     * bloquear siempre). El Docente en autoservicio (sin acceso completo) es de SOLO
     * LECTURA, sin excepción: puede ver el historial completo de sus alumnos (ver el
     * drill-down por id_alumno en obtenerLlegadasTarde) pero no tocar ningún campo ni
     * reenviar correo, ni siquiera de sus propios alumnos — a diferencia del Acudiente,
     * que si conserva esa capacidad operativa sobre sus hijos. Perfiles con acceso al
     * módulo por opción (99 o 101) no se tocan, ya están cubiertos por tener acceso al
     * módulo en primer lugar. */
    private function bloqueadoParaAutoservicio(Request $request, int $id): bool
    {
        $perfil = $request->user()->perfil;

        if ($perfil === self::PERFIL_DOCENTE && !$this->tieneAccesoCompleto($request)) {
            return true;
        }

        if ($perfil !== self::PERFIL_ACUDIENTE) {
            return false;
        }

        $idAlumno = LlegadaTardeModel::where('id', $id)->value('id_alumno');

        if ($idAlumno === null) {
            return true;
        }

        return !in_array($idAlumno, $this->idsHijosDe($request), true);
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
        $perfil = $request->user()->perfil;

        // Autoservicio del Acudiente: ignora cualquier id_alumno/fecha que mande el
        // cliente — siempre se resuelve a SUS hijos. Respeta id_periodo_academico si lo
        // mandan (para revisar un período pasado); sin él, período vigente colapsado a
        // una fila por alumno, igual que ve el staff con acceso completo.
        if ($perfil === self::PERFIL_ACUDIENTE) {
            $idsHijos = $this->idsHijosDe($request);

            $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, null, null, $idsHijos);

            return $this->apiResponse($response);
        }

        // Autoservicio del Docente (sin opción 99, ver PERFIL_DOCENTE arriba): mismo
        // trato que el Acudiente en cuanto al scope (alumnos de SUS cursos en vez de sus
        // hijos), pero el listado general queda acotado al día actual — a diferencia del
        // Acudiente, a quien sí le sirve ver el historial completo de sus hijos de entrada.
        // Mismo criterio de "solo hoy" que el acceso restringido (opción 101) más abajo:
        // se ignora la fecha que mande el cliente para el listado general. Un Docente que
        // además tenga la opción 99 (ej. también Coordinador) no entra acá, cae a la rama
        // general de abajo sin scope ni límite de fecha.
        if ($perfil === self::PERFIL_DOCENTE && !$this->tieneAccesoCompleto($request)) {
            $idsAlumnos = $this->idsAlumnosDeCursosDocente($request);

            $idAlumnoSolicitado = $request->input('id_alumno');

            // Drill-down de UN alumno puntual (modal de historial completo, ver
            // StudentLateArrivalsModal — el docente puede abrirlo en solo lectura desde
            // "Mis llegadas tarde" para revisar todo el historial si lo necesita, aunque
            // el listado general se quede en hoy): se valida que el alumno esté dentro de
            // SUS cursos antes de levantar la restricción de fecha — nunca se confía en el
            // id_alumno del cliente sin verificar el scope primero, igual que
            // bloqueadoParaAutoservicio() para las acciones operativas.
            if ($idAlumnoSolicitado !== null && in_array((int) $idAlumnoSolicitado, $idsAlumnos, true)) {
                $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, (int) $idAlumnoSolicitado, null);

                return $this->apiResponse($response);
            }

            $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, null, now()->toDateString(), $idsAlumnos);

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
        if ($this->bloqueadoParaAutoservicio($request, $id)) {
            return $this->error('No tienes permiso sobre esta llegada tarde', 403);
        }

        $response = $this->llegadas_tarde->reenviarCorreo($id);

        return $this->apiResponse($response);
    }

    // Anotar una observación es operativa, igual que reenviar correo: no requiere
    // acceso completo.
    public function actualizarObservacion(Request $request, int $id){
        if ($this->bloqueadoParaAutoservicio($request, $id)) {
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

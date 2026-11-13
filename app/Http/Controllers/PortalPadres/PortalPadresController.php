<?php

namespace App\Http\Controllers\PortalPadres;

use App\Enums\TipoPerfil;
use App\Http\Controllers\Controller;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Services\Estudiantes\VinculacionAcudienteService;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Portal para padres: autoservicio de un usuario de tipo ACUDIENTE, sin opción en
 * /permisos (igual que "Mis llegadas tarde"). Todo se resuelve a SUS hijos activos en
 * `estudiantes_padres` a partir del usuario autenticado; un id de estudiante que mande el
 * cliente se valida contra ese vínculo antes de usarlo. Solo sale lo que el padre ve: ni
 * documento, ni observaciones, ni la nota libre del personal, ni soportes ni correos.
 */
class PortalPadresController extends Controller
{
    public function __construct(
        private VinculacionAcudienteService $vinculacion,
        private LlegadasTarde $llegadasTarde,
    ) {
    }

    public function hijos(Request $request)
    {
        if (! $request->user()->esTipo(TipoPerfil::ACUDIENTE)) {
            return $this->error('El portal para padres es solo para acudientes', 403);
        }

        $response = $this->vinculacion->estudiantesVinculados($request->user()->id_user);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        $hijos = array_map(
            fn (array $hijo) => array_intersect_key($hijo, array_flip(['id_user', 'nombre', 'apellido', 'curso'])),
            $response['data'],
        );

        return $this->success($response['message'], $hijos);
    }

    /**
     * La semana del portal: historial completo de llegadas tarde (el listado del acudiente en
     * /llegadas-tarde deja solo la última por hijo) y las excepciones de asistencia en clases
     * dictadas (ausente, tarde, permiso).
     */
    public function semana(Request $request, int $idEstudiante)
    {
        if (! $request->user()->esTipo(TipoPerfil::ACUDIENTE)) {
            return $this->error('El portal para padres es solo para acudientes', 403);
        }

        $esHijo = EstudiantesPadre::where('id_acudiente', $request->user()->id_user)
            ->where('id_estudiante', $idEstudiante)
            ->where('activo', 1)
            ->exists();

        if (! $esHijo) {
            return $this->error('Ese estudiante no está vinculado a tu cuenta', 404);
        }

        $response = $this->llegadasTarde->obtenerLlegadasTarde(null, $idEstudiante);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        $llegadas = array_map(fn ($r) => [
            'id' => $r['id'],
            'fecha' => $r['fecha'],
            'hora' => $r['hora'],
            'justificada' => (bool) ($r['justificada'] ?? false),
            'revocado' => (bool) ($r['revocado'] ?? false),
            'limite_alcanzado' => (bool) ($r['limite_alcanzado'] ?? false),
            'total_periodo' => (int) ($r['total_llegadas_tarde_periodo'] ?? 0),
            'periodo' => $r['periodo_academico']['nombre'] ?? null,
        ], collect($response['data']['registros'] ?? [])->toArray());

        $asistencias = DB::table('academico_asistencia_estudiante as ae')
            ->join('academico_asistencia_clase as ac', 'ac.id', '=', 'ae.id_asistencia_clase')
            ->join('academico_horario_clase as hc', 'hc.id', '=', 'ac.id_horario_clase')
            ->join('academico_franja_horaria as f', 'f.id', '=', 'hc.id_franja_horaria')
            ->join('academico_carga_academica as ca', 'ca.id', '=', 'hc.id_carga_academica')
            ->leftJoin('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
            ->leftJoin('academico_asignatura as a', 'a.id', '=', 'da.id_asignatura')
            ->where('ae.id_alumno', $idEstudiante)
            ->where('ac.estado', 'DICTADA')
            ->orderByDesc('ac.fecha')
            ->orderBy('f.hora_inicio')
            ->get(['ae.id', 'ac.fecha', 'ae.estado', 'a.nombre as asignatura', 'f.hora_inicio as hora']);

        return $this->success('Semana obtenida correctamente', ['llegadas' => $llegadas, 'asistencias' => $asistencias]);
    }
}

<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\CargaAcademica;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class CargaAcademicaService extends Service
{
    /**
     * @param bool $silentIfExists Con true, si la carga ya existe la reutiliza en vez de
     * devolver error — usado por el autoservicio del docente (DocenteHorarioService), que
     * solo quiere asegurarse de que la carga exista antes de apartar un horario, sin que
     * "ya existe" sea un fallo. El flujo manual del admin (pestaña "Carga académica")
     * sigue usando el default false: ahí sí es un error, sirve de aviso de duplicado.
     */
    public function añadirCargaAcademicaDocente(int $id_curso, int $id_docente_asignatura, bool $silentIfExists = false)
    {
        try {

            $carga = CargaAcademica::firstOrCreate(
                [
                    'id_docente_asignatura' => $id_docente_asignatura,
                    'id_curso' => $id_curso,
                ]
            );

            if (!$carga->wasRecentlyCreated && !$silentIfExists) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una carga académica para este docente en ese curso.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha creado la carga académica correctamente.",
                'data' => ['id' => $carga->id]
            ];
        } catch (Exception $e) {

            $this->sendError($e, "Error en el servidor al tratar de añadir la carga académica");

            return [
                'error' => true,
                'message' => "Error en el servidor...",
                'data' => []
            ];
        }
    }

    /**
     * Crea (o reutiliza) una carga académica "suelta": docente + curso, SIN pasar por
     * DocenteAsignatura/asignatura — para bloques de horario que no corresponden a
     * ninguna materia puntual (tutoría, disponibilidad, etc.). Mismo criterio de
     * $silentIfExists que añadirCargaAcademicaDocente; usado por
     * HorarioClaseService::añadirHorarioClase al crear un "horario suelto" desde la
     * pestaña Horario (admin) — silentIfExists=true ahí, igual que el autoservicio del
     * docente hace con la carga normal.
     */
    public function añadirCargaAcademicaSuelta(int $id_curso, int $id_docente, bool $silentIfExists = false)
    {
        try {

            $carga = CargaAcademica::firstOrCreate(
                [
                    'id_docente' => $id_docente,
                    'id_curso' => $id_curso,
                ]
            );

            if (!$carga->wasRecentlyCreated && !$silentIfExists) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una carga académica para este docente en ese curso.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha creado la carga académica correctamente.",
                'data' => ['id' => $carga->id]
            ];
        } catch (Exception $e) {

            $this->sendError($e, "Error en el servidor al tratar de añadir la carga académica suelta");

            return [
                'error' => true,
                'message' => "Error en el servidor...",
                'data' => []
            ];
        }
    }

    public function listarCargaAcademicaDocente(int $id_docente, int $estado, ?int $id_curso = null, ?int $id_asignatura = null)
    {
        try {

            $cargaAcademica = CargaAcademica::query()
                ->with([
                    'curso:id,nombre,id_nivel',
                    'curso.nivel:id,nombre',
                    'docenteAsignatura:id,id_docente,id_asignatura,activo',
                    'docenteAsignatura.docente:id_user,nombre,apellido,correo,documento',
                    'docenteAsignatura.asignatura:id,nombre,codigo,abreviatura,color',
                ])->where('activo', $estado)
                ->whereHas('docenteAsignatura', function ($query) use ($id_docente, $id_asignatura) {
                    $query->where('id_docente', $id_docente)
                        ->when($id_asignatura, function ($query) use ($id_asignatura) {
                            $query->where('id_asignatura', $id_asignatura);
                        });
                })
                ->when($id_curso, function ($query) use ($id_curso) {
                    $query->where('id_curso', $id_curso);
                })
                ->get();

            return [
                'error' => false,
                'message' => 'Carga académica obtenida correctamente.',
                'data' => $cargaAcademica,
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al listar la carga académica.');

            return [
                'error' => true,
                'message' => 'Error en el servidor...',
                'data' => [],
            ];
        }
    }

    /**
     * Lista plana (id, nombre) de los cursos donde el docente tiene carga académica
     * activa — para poblar selectores de autoservicio (ej. el filtro de curso en el
     * dashboard de Métricas de Asistencia) sin exponer el resto del listado de carga
     * académica. Misma lógica de scoping que
     * AsistenciaEstudianteService::metricasPorCurso.
     */
    public function obtenerCursosDocente(int $idDocente): array
    {
        try {
            // leftJoin, no join: una carga "suelta" (sin asignatura) no tiene fila en
            // academico_docente_asignatura — el docente se resuelve directo en `ca.id_docente`
            // o, si esa carga sí es por asignatura, en `da.id_docente` (ver
            // CargaAcademicaService::añadirCargaAcademicaSuelta).
            $cursos = DB::table('academico_carga_academica as ca')
                ->leftJoin('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
                ->join('curso as c', 'c.id', '=', 'ca.id_curso')
                ->where(function ($q) use ($idDocente) {
                    $q->where('ca.id_docente', $idDocente)
                        ->orWhere('da.id_docente', $idDocente);
                })
                ->where('ca.activo', 1)
                ->select('c.id', 'c.nombre')
                ->distinct()
                ->orderBy('c.nombre')
                ->get();

            return [
                'error' => false,
                'message' => 'Cursos del docente obtenidos correctamente.',
                'data' => $cursos,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los cursos del docente');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los cursos del docente.',
                'data' => [],
            ];
        }
    }

    public function cambiarEstadoCargaAcademica(array $ids, int $estado)
    {
        try {
            $data = CargaAcademica::whereIn('id', $ids)->update(['activo' => $estado]);

            if ($data == 0) {
                return [
                    'error' => true,
                    'message' => 'No se pudo cambiar el estado de las cargas académicas',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha actualizado $data cargas académicas",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, "Error al cambiar el estado de las cargas académicas.");

            return [
                'error' => true,
                'message' => "Ha ocurrido un error en el servidor. ",
                'data' => []
            ];
        }
    }
}

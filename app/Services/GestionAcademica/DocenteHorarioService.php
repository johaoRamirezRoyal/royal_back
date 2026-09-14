<?php

namespace App\Services\GestionAcademica;

use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\CargaAcademica;
use App\Models\GestionAcademica\DocenteAsignatura;
use App\Models\GestionAcademica\FranjaHoraria;
use App\Models\GestionAcademica\HorarioClase;
use App\Models\Usuarios\Usuario;
use App\Services\Service;
use Exception;

/**
 * Autoservicio de horario: el propio docente arma su horario eligiendo, para cada
 * asignatura que ya tiene asignada, en qué curso y en qué franja disponible del esquema
 * de ese curso la va a dictar. Todos los métodos reciben $id_docente ya resuelto por el
 * controller desde $request->user()->id_user — nunca de un parámetro que venga del
 * cliente, para que el auto-scoping sea real y no solo cosmético en el frontend.
 */
class DocenteHorarioService extends Service
{
    // Fila de `nivel` (tabla legada sin migración propia — nada garantiza que su nombre se
    // mantenga estable) que agrupa 6°-11° — históricamente un solo bucket, antes del split
    // en dos niveles académicos reales (Educación básica secundaria/Media). En esta BD se
    // llama "Secundaria"; en otras pudo quedar como "Bachillerato" tras un rename manual
    // (ver 2026_08_25_020000_add_id_nivel_academico_to_nivel_table). El `id` de la fila no
    // cambia aunque el nombre sí, así que es la comparación robusta — comparar por nombre
    // fue justo lo que rompió idsNivelAcademicoParaNivel() para esta BD (ver
    // 2026_09_12_120000_backfill_id_nivel_academico_for_secundaria).
    private const NIVEL_ID_BACHILLERATO_SECUNDARIA = 4;

    public function __construct(
        private CargaAcademicaService $cargaAcademicaService,
        private HorarioClaseService $horarioClaseService,
    ) {}

    public function verMenu(int $id_docente, int $id_anio_escolar): array
    {
        try {
            $asignaturasDocente = DocenteAsignatura::with('asignatura')
                ->where('id_docente', $id_docente)
                ->where('activo', 1)
                ->get()
                ->filter(fn ($da) => $da->asignatura?->activo)
                ->values();

            // Cursos del/los nivel(es) académico(s) del docente. usuarios.id_nivel/id_nivel_2
            // viven en `nivel` (clasificación general del usuario), pero curso.id_nivel apunta
            // a nivel_academico desde
            // 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico — hay que
            // puentear por nivel.id_nivel_academico para comparar en la misma numeración. Sin
            // nivel asignado (id_nivel=0 en usuarios) o sin id_nivel_academico vinculado se
            // trata igual que "sin cursos", no como "todos los niveles" — fail-closed igual que
            // el resto del autoservicio. Un docente con `id_nivel_2` (ej. Primaria +
            // Bachillerato) ve los cursos de ambos, no solo el nivel principal.
            $docente = Usuario::with(['nivelRelacion', 'nivel2Relacion'])->find($id_docente);
            $idsNivelAcademico = collect([$docente?->nivelRelacion, $docente?->nivel2Relacion])
                ->flatMap(fn ($nivel) => $this->idsNivelAcademicoParaNivel($nivel))
                ->unique()
                ->values()
                ->all();

            if ($asignaturasDocente->isEmpty() || empty($idsNivelAcademico)) {
                return [
                    'error' => false,
                    'message' => 'Menú de horario obtenido correctamente.',
                    'data' => ['cursos' => []],
                ];
            }

            $cursos = Cursos::with('nivel:id,nombre')
                ->where('activo', 1)
                ->whereIn('id_nivel', $idsNivelAcademico)
                ->orderBy('nombre')
                ->get();

            if ($cursos->isEmpty()) {
                return [
                    'error' => false,
                    'message' => 'Menú de horario obtenido correctamente.',
                    'data' => ['cursos' => []],
                ];
            }

            $idsDocenteAsignatura = $asignaturasDocente->pluck('id');

            $cargasExistentes = CargaAcademica::whereIn('id_docente_asignatura', $idsDocenteAsignatura)
                ->whereIn('id_curso', $cursos->pluck('id'))
                ->get()
                ->keyBy(fn ($c) => "{$c->id_curso}-{$c->id_docente_asignatura}");

            $horariosPorCarga = HorarioClase::whereIn('id_carga_academica', $cargasExistentes->pluck('id'))
                ->whereHas('franjaHoraria.esquema', function ($q) use ($id_anio_escolar) {
                    $q->where('id_anio_escolar', $id_anio_escolar);
                })
                ->with('franjaHoraria.diaSemana')
                ->get()
                ->keyBy('id_carga_academica');

            $cursosData = $cursos->map(function ($curso) use ($asignaturasDocente, $cargasExistentes, $horariosPorCarga) {
                return [
                    'id' => $curso->id,
                    'nombre' => $curso->nombre,
                    'nivel' => $curso->nivel ? ['id' => $curso->nivel->id, 'nombre' => $curso->nivel->nombre] : null,
                    'asignaturas' => $asignaturasDocente->map(function ($da) use ($curso, $cargasExistentes, $horariosPorCarga) {
                        $carga = $cargasExistentes->get("{$curso->id}-{$da->id}");
                        $horario = $carga ? $horariosPorCarga->get($carga->id) : null;

                        return [
                            'id' => $da->asignatura->id,
                            'nombre' => $da->asignatura->nombre,
                            'abreviatura' => $da->asignatura->abreviatura,
                            'color' => $da->asignatura->color,
                            'id_docente_asignatura' => $da->id,
                            'id_carga_academica' => $carga->id ?? null,
                            'reservado' => $horario !== null,
                            'franja' => $horario ? [
                                'id' => $horario->franjaHoraria->id,
                                'dia' => $horario->franjaHoraria->diaSemana->nombre,
                                'hora_inicio' => $horario->franjaHoraria->hora_inicio,
                                'hora_fin' => $horario->franjaHoraria->hora_fin,
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values();

            return [
                'error' => false,
                'message' => 'Menú de horario obtenido correctamente.',
                'data' => ['cursos' => $cursosData],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el menú de horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el menú de horario.',
                'data' => [],
            ];
        }
    }

    /** @return array<int, int> */
    private function idsNivelAcademicoParaNivel(?\App\Models\Usuarios\Nivel $nivel): array
    {
        if (!$nivel?->id_nivel_academico) {
            return [];
        }

        $ids = [$nivel->id_nivel_academico];

        // El bucket de 6°-11° (ver NIVEL_ID_BACHILLERATO_SECUNDARIA) puentea 1:1 a
        // nivel_academico Media vía su id_nivel_academico, pero un docente clasificado así
        // históricamente pudo enseñar en cualquiera de los dos niveles académicos reales
        // (Secundaria O Media), no solo el que esa FK 1:1 elige por defecto. `nivel` a
        // propósito no tiene fila propia para Secundaria (ver conversación de diseño), así
        // que no hay otro nivel.id_nivel_academico posible para representarlo — se agrega
        // Secundaria (nivel_academico id 3) a mano solo en este caso puntual.
        if ($nivel->id === self::NIVEL_ID_BACHILLERATO_SECUNDARIA) {
            $ids[] = 3;
        }

        return $ids;
    }

    public function reservar(int $id_docente, int $id_curso, int $id_asignatura, int $id_franja_horaria, int $id_anio_escolar, ?string $descripcion = null): array
    {
        try {
            $docenteAsignatura = DocenteAsignatura::where('id_docente', $id_docente)
                ->where('id_asignatura', $id_asignatura)
                ->where('activo', 1)
                ->first();

            if (!$docenteAsignatura) {
                return [
                    'error' => true,
                    'message' => 'No tienes esta asignatura asignada.',
                    'data' => []
                ];
            }

            $curso = Cursos::find($id_curso);

            if (!$curso) {
                return [
                    'error' => true,
                    'message' => 'El curso no existe.',
                    'data' => []
                ];
            }

            $franja = FranjaHoraria::with('esquema')->find($id_franja_horaria);

            if (!$franja || !$franja->esquema) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria no existe.',
                    'data' => []
                ];
            }

            if ($franja->esquema->id_nivel != $curso->id_nivel || $franja->esquema->id_anio_escolar != $id_anio_escolar) {
                return [
                    'error' => true,
                    'message' => 'Esa franja horaria no pertenece al esquema de este curso.',
                    'data' => []
                ];
            }

            $resultadoCarga = $this->cargaAcademicaService->añadirCargaAcademicaDocente(
                $id_curso,
                $docenteAsignatura->id,
                silentIfExists: true,
            );

            if ($resultadoCarga['error']) {
                return $resultadoCarga;
            }

            $carga = CargaAcademica::with('docenteAsignatura')->find($resultadoCarga['data']['id']);

            $errorDisponibilidad = $this->horarioClaseService->franjaDisponibleParaCarga($franja, $carga);

            if ($errorDisponibilidad) {
                return [
                    'error' => true,
                    'message' => $errorDisponibilidad,
                    'data' => []
                ];
            }

            $horario = HorarioClase::create([
                'id_carga_academica' => $carga->id,
                'id_franja_horaria' => $id_franja_horaria,
                'tipo' => 'CLASE',
                'descripcion' => $descripcion,
            ]);

            return [
                'error' => false,
                'message' => 'Horario reservado correctamente.',
                'data' => $horario->load([
                    'cargaAcademica.curso',
                    'cargaAcademica.docenteAsignatura.asignatura',
                    'franjaHoraria.diaSemana',
                ]),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al reservar el horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al reservar el horario.',
                'data' => []
            ];
        }
    }

    public function misHorarios(int $id_docente): array
    {
        // incluirNoAsignables=true: el docente también ve los recesos/almuerzos globales
        // del esquema junto a sus clases, sin que un admin tenga que recrearlos a mano
        // como HorarioClase (ver HorarioClaseService::mezclarFranjasNoAsignables).
        return $this->horarioClaseService->verHorario($id_docente, null, null, null, true);
    }

    public function actualizarDescripcion(int $id_docente, int $id, ?string $descripcion): array
    {
        try {
            $horario = HorarioClase::with('cargaAcademica.docenteAsignatura')->find($id);

            if (!$horario) {
                return [
                    'error' => true,
                    'message' => 'El horario no existe.',
                    'data' => []
                ];
            }

            if (($horario->cargaAcademica?->docenteAsignatura?->id_docente ?? null) != $id_docente) {
                return [
                    'error' => true,
                    'message' => 'No puedes editar un horario que no es tuyo.',
                    'data' => []
                ];
            }

            $horario->update(['descripcion' => $descripcion]);

            return [
                'error' => false,
                'message' => 'Descripción actualizada correctamente.',
                'data' => $horario
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la descripción del horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la descripción.',
                'data' => []
            ];
        }
    }

    public function eliminar(int $id_docente, array $ids): array
    {
        try {
            $horarios = HorarioClase::whereIn('id', $ids)
                ->with('cargaAcademica.docenteAsignatura')
                ->get();

            if ($horarios->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron horarios para eliminar.',
                    'data' => []
                ];
            }

            $noPropios = $horarios->filter(
                fn ($h) => ($h->cargaAcademica?->docenteAsignatura?->id_docente ?? null) != $id_docente
            );

            if ($noPropios->isNotEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No puedes eliminar horarios que no son tuyos.',
                    'data' => []
                ];
            }

            $eliminados = HorarioClase::whereIn('id', $ids)->delete();

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} horario(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar tus horarios');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar tus horarios.',
                'data' => []
            ];
        }
    }
}

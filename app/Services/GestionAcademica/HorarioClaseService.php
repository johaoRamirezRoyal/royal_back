<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\CargaAcademica;
use App\Models\GestionAcademica\FranjaHoraria;
use App\Models\GestionAcademica\HorarioClase;
use App\Services\Service;
use Exception;

class HorarioClaseService extends Service
{
    /**
     * Añade un horario de clase.
     *
     * @param array $data
     * @return array
     */
    public function añadirHorarioClase(array $data): array
    {
        try {

            // Verificar que exista la franja
            $franja = FranjaHoraria::find($data['id_franja_horaria']);

            if (!$franja) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria no existe.',
                    'data' => []
                ];
            }

            // Validar carga académica
            if ($data['tipo'] === 'CLASE') {

                if (empty($data['id_carga_academica'])) {
                    return [
                        'error' => true,
                        'message' => 'Debe indicar la carga académica para una clase.',
                        'data' => []
                    ];
                }

                $carga = CargaAcademica::with('docenteAsignatura.asignatura')->find($data['id_carga_academica']);

                if (!$carga) {
                    return [
                        'error' => true,
                        'message' => 'La carga académica no existe.',
                        'data' => []
                    ];
                }

                if (!$carga->activo) {
                    return [
                        'error' => true,
                        'message' => 'No se puede definir una clase de una carga académica desactivada.',
                        'data' => []
                    ];
                }

                if (!$carga->docenteAsignatura?->asignatura?->activo) {
                    return [
                        'error' => true,
                        'message' => 'No se puede definir una clase de una asignatura desactivada.',
                        'data' => []
                    ];
                }
            } elseif (!empty($data['id_carga_academica'])) {

                // Tipo distinto de CLASE pero con un docente específico seleccionado
                // (vía su carga académica): validar que exista y esté activa, igual
                // que para CLASE, en vez de descartar la selección.
                $carga = CargaAcademica::with('docenteAsignatura')->find($data['id_carga_academica']);

                if (!$carga) {
                    return [
                        'error' => true,
                        'message' => 'La carga académica no existe.',
                        'data' => []
                    ];
                }

                if (!$carga->activo) {
                    return [
                        'error' => true,
                        'message' => 'No se puede asignar el bloque a una carga académica desactivada.',
                        'data' => []
                    ];
                }
            }

            if (!empty($data['id_carga_academica'])) {

                // El cruce se valida por curso y por docente, no por franja global, ya
                // que otro curso con otro docente sí puede compartir la misma franja
                // (ver FranjaHorariaService::verFranjasHorarias). Lo que no puede pasar
                // es que el MISMO curso o el MISMO docente tengan dos actividades a la vez.
                $cursoOcupado = HorarioClase::where('id_franja_horaria', $data['id_franja_horaria'])
                    ->whereHas('cargaAcademica', function ($q) use ($carga) {
                        $q->where('id_curso', $carga->id_curso);
                    })
                    ->exists();

                if ($cursoOcupado) {
                    return [
                        'error' => true,
                        'message' => 'El curso ya tiene una actividad asignada en esa franja horaria.',
                        'data' => []
                    ];
                }

                $docenteOcupado = HorarioClase::where('id_franja_horaria', $data['id_franja_horaria'])
                    ->whereHas('cargaAcademica.docenteAsignatura', function ($q) use ($carga) {
                        $q->where('id_docente', $carga->docenteAsignatura->id_docente);
                    })
                    ->exists();

                if ($docenteOcupado) {
                    return [
                        'error' => true,
                        'message' => 'El docente ya tiene una actividad asignada en esa franja horaria.',
                        'data' => []
                    ];
                }
            } else {

                // Sin docente (receso, almuerzo, etc.): la franja es compartida por
                // todo el colegio, así que solo puede tener una actividad.
                $existeHorario = HorarioClase::where('id_franja_horaria', $data['id_franja_horaria'])->exists();

                if ($existeHorario) {
                    return [
                        'error' => true,
                        'message' => 'La franja horaria ya tiene una actividad asignada.',
                        'data' => []
                    ];
                }
            }

            $horario = HorarioClase::create($data);

            return [
                'error' => false,
                'message' => 'Horario creado correctamente.',
                'data' => $horario->load([
                    'cargaAcademica',
                    'franjaHoraria'
                ])
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear el horario de clase');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el horario.',
                'data' => []
            ];
        }
    }

    /**
     * Obtiene el horario de clases con filtros opcionales.
     *
     * Permite consultar el horario completo o filtrarlo por docente, curso,
     * asignatura y/o día de la semana. Si no se envía ningún filtro, retorna
     * todas las actividades registradas en el horario.
     *
     * Filtros disponibles:
     * - id_docente: Muestra únicamente las actividades asignadas a un docente.
     * - id_curso: Muestra únicamente las actividades de un curso.
     * - id_asignatura: Muestra únicamente las actividades de una asignatura.
     * - id_dia_semana: Muestra únicamente las actividades de un día de la semana.
     *
     * Los filtros pueden combinarse entre sí.
     *
     * Ejemplos:
     * - Todos los horarios.
     * - Horario de un docente.
     * - Horario de un curso.
     * - Horario de una asignatura.
     * - Horario de un día específico.
     * - Horario de un docente en un curso específico.
     *
     * La información retornada incluye:
     * - Datos del horario.
     * - Franja horaria.
     * - Día de la semana.
     * - Carga académica (si aplica).
     * - Curso.
     * - Docente.
     * - Asignatura.
     *
     * @param int|null $id_docente ID del docente a filtrar.
     * @param int|null $id_curso ID del curso a filtrar.
     * @param int|null $id_asignatura ID de la asignatura a filtrar.
     * @param int|null $id_dia_semana ID del día de la semana a filtrar.
     *
     * @return array{
     *     error: bool,
     *     message: string,
     *     data: array
     * }
     */
    public function verHorario(
        ?int $id_docente,
        ?int $id_curso,
        ?int $id_asignatura,
        ?int $id_dia_semana
    ): array {

        try {

            $horario = HorarioClase::query()

                ->with([

                    'franjaHoraria:id,id_dia_semana,hora_inicio,hora_fin,orden',

                    'franjaHoraria.diaSemana:id,nombre,abreviatura',

                    'cargaAcademica:id,id_docente_asignatura,id_curso',

                    'cargaAcademica.curso:id,nombre',

                    'cargaAcademica.docenteAsignatura:id,id_docente,id_asignatura',

                    'cargaAcademica.docenteAsignatura.docente:id_user,nombre,apellido',

                    'cargaAcademica.docenteAsignatura.asignatura:id,nombre,codigo,abreviatura,color'
                ])

                ->when($id_dia_semana, function ($query) use ($id_dia_semana) {

                    $query->whereHas('franjaHoraria', function ($q) use ($id_dia_semana) {

                        $q->where('id_dia_semana', $id_dia_semana);
                    });
                })

                ->when($id_curso, function ($query) use ($id_curso) {

                    $query->whereHas('cargaAcademica', function ($q) use ($id_curso) {

                        $q->where('id_curso', $id_curso);
                    });
                })

                // Las franjas sin carga académica (receso, almuerzo, planeación, etc.) no
                // pertenecen a ningún docente en particular — se muestran siempre, sin
                // filtrar por id_docente, igual que ya se hace con el chequeo de "activo".
                ->when($id_docente, function ($query) use ($id_docente) {

                    $query->where(function ($q) use ($id_docente) {

                        $q->whereNull('id_carga_academica')
                            ->orWhereHas(
                                'cargaAcademica.docenteAsignatura',
                                function ($q2) use ($id_docente) {

                                    $q2->where('id_docente', $id_docente);
                                }
                            );
                    });
                })

                ->when($id_asignatura, function ($query) use ($id_asignatura) {

                    $query->whereHas(
                        'cargaAcademica.docenteAsignatura',
                        function ($q) use ($id_asignatura) {

                            $q->where('id_asignatura', $id_asignatura);
                        }
                    );
                })

                // Oculta clases de carga académica o asignatura desactivada; las franjas
                // sin carga académica (descansos, etc.) no aplican y se muestran siempre.
                ->where(function ($query) {

                    $query->whereNull('id_carga_academica')
                        ->orWhere(function ($q) {

                            $q->whereHas('cargaAcademica', function ($q2) {

                                $q2->where('activo', 1);
                            })->whereHas('cargaAcademica.docenteAsignatura.asignatura', function ($q2) {

                                $q2->where('activo', 1);
                            });
                        });
                })

                ->join(
                    'academico_franja_horaria',
                    'academico_horario_clase.id_franja_horaria',
                    '=',
                    'academico_franja_horaria.id'
                )

                ->join(
                    'dias_semana',
                    'academico_franja_horaria.id_dia_semana',
                    '=',
                    'dias_semana.id'
                )

                ->select('academico_horario_clase.*')

                ->orderBy('dias_semana.orden')

                ->orderBy('academico_franja_horaria.orden')

                ->get();

            return [

                'error' => false,

                'message' => 'Horario obtenido correctamente.',

                'data' => $horario

            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al obtener el horario');

            return [

                'error' => true,

                'message' => 'Error al obtener el horario.',

                'data' => []

            ];
        }
    }

    /**
     * Elimina uno o varios horarios.
     *
     * @param array $ids IDs de los horarios a eliminar.
     * @return array{
     *     error: bool,
     *     message: string,
     *     data: array
     * }
     */
    public function eliminarHorarios(array $ids): array
    {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe indicar al menos un horario para eliminar.',
                    'data' => []
                ];
            }

            $eliminados = HorarioClase::whereIn('id', $ids)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron horarios para eliminar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} horario(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al eliminar los horarios');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar los horarios.',
                'data' => []
            ];
        }
    }
}

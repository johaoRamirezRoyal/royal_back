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

            $errorDisponibilidad = $this->franjaDisponibleParaCarga($franja, $carga ?? null);

            if ($errorDisponibilidad) {
                return [
                    'error' => true,
                    'message' => $errorDisponibilidad,
                    'data' => []
                ];
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
     * Valida que una franja horaria esté libre para la carga académica dada (o, sin
     * carga, que la franja esté completamente libre — recesos/almuerzos compartidos por
     * todo el colegio). Devuelve el mensaje de error si está ocupada, o null si está
     * disponible. Compartido por el flujo manual del admin (añadirHorarioClase) y el
     * autoservicio del docente (DocenteHorarioService::reservar) — ambos ya cargan la
     * FranjaHoraria antes de llamar este método, así que se reutiliza el objeto en vez
     * de volver a consultarla por id.
     *
     * El cruce se valida por curso y por docente, no por franja global, ya que otro
     * curso con otro docente sí puede compartir la misma franja (ver
     * FranjaHorariaService::verFranjasHorarias). Lo que no puede pasar es que el MISMO
     * curso o el MISMO docente tengan dos actividades a la vez.
     *
     * El chequeo compara por HORA REAL (mismo día + intervalo cruzado), no por
     * id_franja_horaria exacto: un docente puede dictar en curso de esquemas distintos
     * (Primaria/Bachillerato con franjas numeradas distinto), y dos franjas con ids
     * diferentes pero la misma hora real sí son un cruce igual de real.
     */
    public function franjaDisponibleParaCarga(FranjaHoraria $franja, ?CargaAcademica $carga): ?string
    {
        if (!$franja->asignable) {
            return 'Esta franja horaria no es asignable' . ($franja->etiqueta ? " ({$franja->etiqueta})." : '.');
        }

        if ($carga) {
            $cursoOcupado = $this->existeCruceHorarioClase($franja, function ($q) use ($carga) {
                $q->whereHas('cargaAcademica', function ($q2) use ($carga) {
                    $q2->where('id_curso', $carga->id_curso);
                });
            });

            if ($cursoOcupado) {
                return 'El curso ya tiene una actividad asignada en esa franja horaria.';
            }

            $docenteOcupado = $this->existeCruceHorarioClase($franja, function ($q) use ($carga) {
                $q->whereHas('cargaAcademica.docenteAsignatura', function ($q2) use ($carga) {
                    $q2->where('id_docente', $carga->docenteAsignatura->id_docente);
                });
            });

            if ($docenteOcupado) {
                return 'El docente ya tiene una actividad asignada en esa franja horaria.';
            }

            return null;
        }

        // Sin docente (receso, almuerzo, etc.): la franja es compartida por todo el
        // colegio, así que solo puede tener una actividad.
        $existeHorario = HorarioClase::where('id_franja_horaria', $franja->id)->exists();

        return $existeHorario ? 'La franja horaria ya tiene una actividad asignada.' : null;
    }

    /**
     * Existe algún HorarioClase (filtrado por $scope, curso o docente) cuya franja caiga
     * el mismo día y se cruce en hora con $franja — intervalo exclusivo, para no marcar
     * como cruce a franjas contiguas (una termina justo cuando empieza la otra).
     */
    private function existeCruceHorarioClase(FranjaHoraria $franja, callable $scope): bool
    {
        return HorarioClase::query()
            ->tap($scope)
            ->whereHas('franjaHoraria', function ($q) use ($franja) {
                $q->where('id_dia_semana', $franja->id_dia_semana)
                    ->where('hora_inicio', '<', $franja->hora_fin)
                    ->where('hora_fin', '>', $franja->hora_inicio);
            })
            ->exists();
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
     * @param bool $incluirNoAsignables Si es true, agrega al resultado (como bloques de
     *   solo lectura, id negativo, sin id_carga_academica) las franjas asignable=false del
     *   mismo esquema que ya aparecen en el resultado — receso/almuerzo globales del
     *   esquema, sin que un admin tenga que recrearlos a mano como HorarioClase. Por
     *   defecto en false para no alterar el contrato de quien ya consume este método sin
     *   pedirlo explícitamente (ej. Attendances, que usa `tipo` para filtrar clases reales).
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
        ?int $id_dia_semana,
        bool $incluirNoAsignables = false
    ): array {

        try {

            $horario = HorarioClase::query()

                ->with([

                    'franjaHoraria:id,id_esquema,id_dia_semana,hora_inicio,hora_fin,orden',

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

            $data = $incluirNoAsignables
                ? $this->mezclarFranjasNoAsignables($horario, $id_dia_semana)
                : $horario;

            return [

                'error' => false,

                'message' => 'Horario obtenido correctamente.',

                'data' => $data

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
     * Mezcla en $horario (colección de HorarioClase reales) las franjas asignable=false del
     * mismo esquema que ya aparecen ahí, como bloques de solo lectura (receso, almuerzo,
     * etc.), ordenados junto a los reales por día+hora. El esquema se infiere de las
     * propias franjas de $horario (franjaHoraria.id_esquema) — si $horario viene vacío no
     * hay de dónde inferirlo y no se agrega nada (ej. un docente que aún no tiene ninguna
     * clase armada). Cada bloque sintético usa id = -id_franja_horaria (nunca choca con un
     * id real de HorarioClase, que es autoincremental positivo) para que el frontend pueda
     * usarlo como key/rowKey sin tocar el tipo de `id`, y se distingue con
     * `es_no_asignable: true` — no se puede editar/eliminar, es puramente informativo.
     *
     * Un docente puede tener clases en más de un esquema a la vez (ej. Secundaria y Media,
     * o cualquier combinación — ver DocenteHorarioService::verMenu) y todos los esquemas
     * comparten la misma numeración de horas (FranjaHorarioSeeder les da los mismos
     * bloques). Si se agregara sin más el receso de CADA esquema tocado, un receso del
     * esquema B podía aparecer en la misma celda día+hora donde el docente ya tiene una
     * clase real del esquema A — un "cruce" que en realidad nunca ocurre (son horarios de
     * esquemas distintos, no del mismo día real del docente). Por eso se excluye cualquier
     * franja no asignable cuyo día+hora ya coincida con una clase real de $horario.
     */
    private function mezclarFranjasNoAsignables($horario, ?int $id_dia_semana)
    {
        $idsEsquema = $horario->pluck('franjaHoraria.id_esquema')->filter()->unique()->values();

        if ($idsEsquema->isEmpty()) {
            return $horario;
        }

        $ocupados = $horario
            ->map(fn ($h) => ($h->franjaHoraria->id_dia_semana ?? null) . '-' . ($h->franjaHoraria->hora_inicio ?? null))
            ->unique()
            ->flip();

        $franjasNoAsignables = FranjaHoraria::whereIn('id_esquema', $idsEsquema)
            ->where('asignable', false)
            ->when($id_dia_semana, fn ($q) => $q->where('id_dia_semana', $id_dia_semana))
            ->with('diaSemana:id,nombre,abreviatura')
            ->get()
            ->reject(fn (FranjaHoraria $franja) => isset($ocupados["{$franja->id_dia_semana}-{$franja->hora_inicio}"]));

        if ($franjasNoAsignables->isEmpty()) {
            return $horario;
        }

        $bloques = $franjasNoAsignables->map(function (FranjaHoraria $franja) {
            return [
                'id' => -$franja->id,
                'id_carga_academica' => null,
                'id_franja_horaria' => $franja->id,
                'tipo' => null,
                'descripcion' => null,
                'es_no_asignable' => true,
                'etiqueta' => $franja->etiqueta,
                'color' => $franja->color,
                'franja_horaria' => [
                    'id' => $franja->id,
                    'id_dia_semana' => $franja->id_dia_semana,
                    'hora_inicio' => $franja->hora_inicio,
                    'hora_fin' => $franja->hora_fin,
                    'orden' => $franja->orden,
                    'dia_semana' => $franja->diaSemana,
                ],
                'carga_academica' => null,
            ];
        });

        // dias_semana.id coincide con su orden (1=Lunes...7=Domingo, ver DiaSemanaSeeder) —
        // se ordena directo por id_dia_semana sin otro join, igual para modelos reales
        // (franjaHoraria->id_dia_semana) y arrays sintéticos (franja_horaria.id_dia_semana).
        $ordenar = function ($item) {
            if (is_array($item)) {
                return [$item['franja_horaria']['id_dia_semana'] ?? 0, $item['franja_horaria']['hora_inicio'] ?? ''];
            }
            return [$item->franjaHoraria->id_dia_semana ?? 0, $item->franjaHoraria->hora_inicio ?? ''];
        };

        return $horario->concat($bloques)
            ->sort(fn ($a, $b) => $ordenar($a) <=> $ordenar($b))
            ->values();
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

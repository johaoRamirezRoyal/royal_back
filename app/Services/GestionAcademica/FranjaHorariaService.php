<?php

namespace App\Services\GestionAcademica;

use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\CargaAcademica;
use App\Models\GestionAcademica\EsquemaHorario;
use App\Models\GestionAcademica\FranjaHoraria;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class FranjaHorariaService extends Service
{
    /**
     * Dos franjas se cruzan solo si sus rangos se superponen de verdad — que una termine
     * exactamente cuando la otra empieza (ej. 07:30-08:20 seguida de 08:20-09:10) NO es un
     * cruce, es el caso normal de una grilla sin huecos. La comparación anterior usaba
     * BETWEEN (inclusivo en ambos extremos), así que ese borde compartido se detectaba
     * como cruce y bloqueaba crear/editar cualquier franja pegada a otra.
     */
    private function existeCruceHorario($query, string $horaInicio, string $horaFin): bool
    {
        return $query
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->exists();
    }

    /**
     * Resuelve el id_esquema a consultar: directo si se envía, o derivado del nivel del
     * curso + año escolar (usado por la página de autoservicio del docente y por la
     * pestaña "Horario", que solo conocen el curso, no el esquema).
     */
    private function resolverIdEsquema(?int $id_esquema, ?int $id_curso, ?int $id_anio_escolar): ?int
    {
        if ($id_esquema) {
            return $id_esquema;
        }

        if (!$id_curso || !$id_anio_escolar) {
            return null;
        }

        $curso = Cursos::find($id_curso);

        if (!$curso || !$curso->id_nivel) {
            return null;
        }

        return EsquemaHorario::where('id_nivel', $curso->id_nivel)
            ->where('id_anio_escolar', $id_anio_escolar)
            ->value('id');
    }

    public function verFranjasHorarias(
        ?int $id_esquema,
        ?int $id_curso = null,
        ?int $id_anio_escolar = null,
        ?int $id_dia_semana = null,
        ?bool $disponible = null,
        ?int $id_carga_academica = null
    ) {
        try {
            $idEsquemaResuelto = $this->resolverIdEsquema($id_esquema, $id_curso, $id_anio_escolar);

            if (!$idEsquemaResuelto) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna franja horaria',
                    'data' => []
                ];
            }

            $franjaHoraria = FranjaHoraria::query()
                ->join(
                    'dias_semana',
                    'dias_semana.id',
                    '=',
                    'academico_franja_horaria.id_dia_semana'
                )
                ->select(
                    'dias_semana.nombre',
                    'dias_semana.abreviatura',
                    'dias_semana.orden as orden_dia',
                    'academico_franja_horaria.*',
                )->where('id_esquema', $idEsquemaResuelto)
                ->when($id_dia_semana, function ($query) use ($id_dia_semana) {
                    $query->where('id_dia_semana', $id_dia_semana);
                })->when($disponible, function ($query) use ($id_carga_academica) {

                    // No asignable (receso, almuerzo, etc. marcados directo en la franja):
                    // nunca aparece como disponible, sin importar la carga académica.
                    $query->where('asignable', true);

                    // Sin id_carga_academica: excluye franjas ocupadas por cualquier clase.
                    // Con id_carga_academica: excluye las franjas donde YA hay clase para
                    // ese CURSO (con cualquier docente) o para ese DOCENTE (en cualquier
                    // curso), ya que ninguno de los dos puede estar en dos clases a la vez.
                    // Ocupado por otro curso con otro docente sigue apareciendo como libre.
                    $carga = $id_carga_academica
                        ? CargaAcademica::with('docenteAsignatura')->find($id_carga_academica)
                        : null;

                    $query->whereDoesntHave('horarioClase', function ($q) use ($carga) {
                        $q->when($carga, function ($q) use ($carga) {
                            $q->whereHas('cargaAcademica', function ($q2) use ($carga) {
                                $q2->where('id_curso', $carga->id_curso)
                                    ->orWhereHas('docenteAsignatura', function ($q3) use ($carga) {
                                        $q3->where('id_docente', $carga->docenteAsignatura?->id_docente);
                                    });
                            });
                        });
                    });
                })
                ->orderBy('dias_semana.orden')
                ->orderBy('academico_franja_horaria.hora_inicio')
                ->get();

            if ($franjaHoraria->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontró ninguna franja horaria",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se encontraron las franjas horarias",
                'data' => $franjaHoraria->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener las franjas horarias");
            return [
                'error' => true,
                'message' => "Error en el servidor para las franjas horarias",
                'data' => []
            ];
        }
    }

    /**
     * Método para añadir una franja horaria.
     *
     * El array recibe:
     * - id_esquema
     * - id_dia_semana
     * - hora_inicio
     * - hora_fin
     * - orden
     *
     * Validaciones:
     * - La hora de fin debe ser mayor que la de inicio.
     * - No puede existir otra franja con el mismo orden para el mismo esquema y día.
     * - No puede solaparse con otra franja del mismo día.
     *
     * @param array $data
     * @return array
     */
    public function añadirFranjaHoraria(array $data): array
    {
        try {

            $esquema = EsquemaHorario::find($data['id_esquema']);

            if (!$esquema) {
                return [
                    'error' => true,
                    'message' => 'El esquema de horario no existe.',
                    'data' => []
                ];
            }

            // id_anio_escolar queda deprecado (ver 2026_08_21_130100_add_id_esquema_...)
            // pero la columna sigue siendo NOT NULL en la tabla real — se deriva del
            // esquema para no requerir una migración destructiva sobre esa columna.
            $data['id_anio_escolar'] = $esquema->id_anio_escolar;

            // Validar horas
            if (strtotime($data['hora_fin']) <= strtotime($data['hora_inicio'])) {
                return [
                    'error' => true,
                    'message' => 'La hora de fin debe ser mayor que la hora de inicio.',
                    'data' => []
                ];
            }

            // Validar orden
            $existeOrden = FranjaHoraria::where('id_esquema', $data['id_esquema'])
                ->where('id_dia_semana', $data['id_dia_semana'])
                ->where('orden', $data['orden'])
                ->exists();

            if ($existeOrden) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una franja con ese orden para el día seleccionado.',
                    'data' => []
                ];
            }

            // Validar solapamiento de horarios
            $hayCruce = $this->existeCruceHorario(
                FranjaHoraria::where('id_esquema', $data['id_esquema'])->where('id_dia_semana', $data['id_dia_semana']),
                $data['hora_inicio'],
                $data['hora_fin']
            );

            if ($hayCruce) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria se cruza con otra ya existente.',
                    'data' => []
                ];
            }

            $franjaNueva = FranjaHoraria::create($data);

            return [
                'error' => false,
                'message' => 'Franja horaria creada correctamente.',
                'data' => $franjaNueva->toArray(),
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la franja horaria.',
                'data' => []
            ];
        }
    }


    /**
     * Método para actualizar las franjas horarias.
     *
     * Permite actualizar:
     * - Esquema de horario.
     *
     * @param array $ids
     * @param int|null $id_esquema
     * @return array
     */
    public function actualizarFranjaHoraria(
        array $ids,
        ?int $id_esquema
    ): array {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una franja horaria.',
                    'data' => []
                ];
            }

            if ($id_esquema === null) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos un campo para actualizar.',
                    'data' => []
                ];
            }

            $dataActualizar = [
                'id_esquema' => $id_esquema,
            ];

            $actualizados = FranjaHoraria::whereIn('id', $ids)
                ->update($dataActualizar);

            if ($actualizados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna franja horaria para actualizar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se actualizaron {$actualizados} franja(s) horaria(s).",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la franja horaria.',
                'data' => []
            ];
        }
    }

    /**
     * Actualiza el orden de las franjas horarias.
     * El array de $franjas debe ser -> [['id' => ?, 'orden' => ?], ['id' => ?, 'orden' => ?]]
     * @param array $franjas
     * @return array
     */
    public function actualizarOrdenFranjasHorarias(array $franjas): array
    {
        try {

            if (empty($franjas)) {
                return [
                    'error' => true,
                    'message' => 'No se recibieron franjas para actualizar.',
                    'data' => []
                ];
            }

            foreach ($franjas as $franja) {
                if (!isset($franja['id']) || !isset($franja['orden'])) {
                    return [
                        'error' => true,
                        'message' => 'Cada franja debe contener id y orden.',
                        'data' => []
                    ];
                }
            }

            DB::beginTransaction();

            // Dos pasadas: primero a valores temporales negativos (únicos por id) para
            // no chocar con el uq_franja_horaria (id_anio_escolar, id_dia_semana, orden)
            // mientras otras filas del mismo grupo aún tienen su orden viejo.
            foreach ($franjas as $franja) {
                FranjaHoraria::where('id', $franja['id'])
                    ->update(['orden' => -$franja['id']]);
            }

            foreach ($franjas as $franja) {
                FranjaHoraria::where('id', $franja['id'])
                    ->update(['orden' => $franja['orden']]);
            }

            DB::commit();

            return [
                'error' => false,
                'message' => 'Orden de las franjas actualizado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {

            DB::rollBack();

            $this->sendError($e, 'Error al actualizar el orden de las franjas horarias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el orden.',
                'data' => []
            ];
        }
    }

    public function eliminarFranjaHoraria(array $ids): array
    {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una franja para eliminar.',
                    'data' => []
                ];
            }

            $eliminadas = FranjaHoraria::whereIn('id', $ids)->delete();

            if ($eliminadas === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron franjas para eliminar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminadas} franja(s) horaria(s).",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al eliminar las franjas horarias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las franjas horarias.',
                'data' => []
            ];
        }
    }

    /**
     * Actualiza la hora de inicio y/o fin de una franja horaria, y/o si es asignable
     * (con su color y etiqueta de identificación — ver migración
     * add_asignable_color_to_academico_franja_horaria_table).
     *
     * @param int $id
     * @param string|null $hora_inicio Formato H:i:s
     * @param string|null $hora_fin Formato H:i:s
     * @param bool|null $asignable
     * @param string|null $color
     * @param string|null $etiqueta
     * @param bool|null $aplicarTodosDias Si es true, replica asignable/color/etiqueta (no
     *   la hora) en toda franja de OTRO día del mismo esquema cuyo hora_inicio/hora_fin
     *   coincida exactamente con el de esta franja (ej. marcar el receso de las 10:00-10:15
     *   una sola vez y aplicarlo a todos los días que tengan esa misma franja).
     * @return array
     */
    public function actualizarHorarioFranja(
        int $id,
        ?string $hora_inicio,
        ?string $hora_fin,
        ?bool $asignable = null,
        ?string $color = null,
        ?string $etiqueta = null,
        ?bool $aplicarTodosDias = null
    ): array {
        try {

            if ($hora_inicio === null && $hora_fin === null && $asignable === null && $color === null && $etiqueta === null) {
                return [
                    'error' => true,
                    'message' => 'Debe indicar al menos un campo para actualizar.',
                    'data' => []
                ];
            }

            $franja = FranjaHoraria::find($id);

            if (!$franja) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria no existe.',
                    'data' => []
                ];
            }

            // Deshabilitar manualmente una franja "no asignable" (receso, almuerzo, etc.)
            // se desmarca (vuelve a un bloque normal reservable) en vez de eliminarse — ver
            // desmarcarFranjaNoAsignable().
            if ($franja->asignable === false && $asignable === true) {
                return $this->desmarcarFranjaNoAsignable($franja);
            }

            $nuevaHoraInicio = $hora_inicio ?? $franja->hora_inicio;
            $nuevaHoraFin = $hora_fin ?? $franja->hora_fin;

            // Validar rango horario
            if (strtotime($nuevaHoraFin) <= strtotime($nuevaHoraInicio)) {
                return [
                    'error' => true,
                    'message' => 'La hora de fin debe ser mayor que la hora de inicio.',
                    'data' => []
                ];
            }

            // Validar solapamiento con otras franjas del mismo esquema — no basta con
            // id_anio_escolar: dos esquemas distintos (ej. Primaria y Bachillerato) pueden
            // compartir año escolar y sus horarios se solapan en el reloj sin ser la misma
            // grilla, lo que disparaba "cruce" falsos entre franjas de esquemas distintos.
            // Franjas legacy sin id_esquema (creadas antes de esa columna) siguen
            // comparándose por id_anio_escolar, como antes.
            $hayCruce = $this->existeCruceHorario(
                FranjaHoraria::where('id_dia_semana', $franja->id_dia_semana)
                    ->where('id', '<>', $franja->id)
                    ->when(
                        $franja->id_esquema !== null,
                        fn ($q) => $q->where('id_esquema', $franja->id_esquema),
                        fn ($q) => $q->where('id_anio_escolar', $franja->id_anio_escolar)
                    ),
                $nuevaHoraInicio,
                $nuevaHoraFin
            );

            if ($hayCruce) {
                return [
                    'error' => true,
                    'message' => 'El horario se cruza con otra franja horaria.',
                    'data' => []
                ];
            }

            // No se puede marcar como no asignable una franja que ya tiene una clase (o
            // receso/almuerzo previamente asignado vía HorarioClase) — eso dejaría esa
            // asignación "huérfana": visible en el horario de un docente/curso pero
            // imposible de reasignar o gestionar desde franjas horarias.
            if ($asignable === false && $franja->horarioClase()->exists()) {
                return [
                    'error' => true,
                    'message' => 'Esta franja ya tiene una clase asignada; elimínala o reprográmala antes de marcarla como no asignable.',
                    'data' => []
                ];
            }

            // Una franja que ya depende de un pivote (id_franja_pivote no nulo) no es un
            // pivote independiente — no puede usarse para "aplicar a todos los días" otra
            // vez, porque eso la convertiría en el origen de una nueva marcación mientras
            // sigue heredando la del pivote original. Hay que eliminarla (nullOnDelete
            // libera a cualquier dependiente que tuviera) y agregar la franja deseada aparte.
            if ($aplicarTodosDias === true && $franja->id_franja_pivote !== null) {
                return [
                    'error' => true,
                    'message' => 'Esta franja depende de otro horario pivote; no se puede usar como pivote para aplicar a todos los días. Elimínala y agrega la franja deseada por separado.',
                    'data' => []
                ];
            }

            $dataActualizar = [
                'hora_inicio' => $nuevaHoraInicio,
                'hora_fin' => $nuevaHoraFin,
            ];

            if ($asignable !== null) {
                $dataActualizar['asignable'] = $asignable;
            }
            if ($color !== null) {
                $dataActualizar['color'] = $color;
            }
            if ($etiqueta !== null) {
                $dataActualizar['etiqueta'] = $etiqueta;
            }

            $franja->update($dataActualizar);

            $replicadas = 0;
            $omitidasPorClase = 0;
            $omitidasPorPivote = 0;

            if ($aplicarTodosDias === true) {
                $camposReplicar = array_diff_key($dataActualizar, ['hora_inicio' => null, 'hora_fin' => null]);

                if (!empty($camposReplicar)) {
                    $marcandoNoAsignable = ($camposReplicar['asignable'] ?? null) === false;

                    // Marcar como no asignable vía "aplicar a todos los días" deja a $franja
                    // como el pivote del que dependen las demás — así una franja que ya
                    // depende de OTRO pivote no se puede pisar silenciosamente (ver el guard
                    // más arriba para cuando la propia $franja es la dependiente).
                    if ($marcandoNoAsignable) {
                        $camposReplicar['id_franja_pivote'] = $franja->id;
                    }

                    $candidatas = FranjaHoraria::where('hora_inicio', $nuevaHoraInicio)
                        ->where('hora_fin', $nuevaHoraFin)
                        ->where('id', '<>', $franja->id)
                        ->when(
                            $franja->id_esquema !== null,
                            fn ($q) => $q->where('id_esquema', $franja->id_esquema),
                            fn ($q) => $q->where('id_anio_escolar', $franja->id_anio_escolar)
                        )
                        ->get();

                    // Igual que con la franja principal: no se replica "no asignable" sobre
                    // una franja que ya tiene clase, ni sobre una que ya depende de OTRO
                    // pivote (no de este mismo, eso sí se re-aplica sin problema) — ambas se
                    // omiten y se reportan, en vez de dejar una huérfana o pisar la
                    // dependencia de otro pivote sin avisar.
                    $conClaseAsignada = $marcandoNoAsignable
                        ? $candidatas->filter(fn (FranjaHoraria $f) => $f->horarioClase()->exists())
                        : collect();
                    $conOtroPivote = $marcandoNoAsignable
                        ? $candidatas->filter(fn (FranjaHoraria $f) => $f->id_franja_pivote !== null && $f->id_franja_pivote !== $franja->id)
                        : collect();
                    $omitidasPorClase = $conClaseAsignada->count();
                    $omitidasPorPivote = $conOtroPivote->count();
                    $idsOmitidas = $conClaseAsignada->pluck('id')->merge($conOtroPivote->pluck('id'));

                    $idsAReplicar = $candidatas->pluck('id')->diff($idsOmitidas);

                    if ($idsAReplicar->isNotEmpty()) {
                        FranjaHoraria::whereIn('id', $idsAReplicar)->update($camposReplicar);
                        // No se usa el valor de retorno de update(): MySQL cuenta "filas
                        // afectadas" como filas cuyo valor realmente cambió, no filas que
                        // coincidieron con el WHERE — si un día ya tenía exactamente estos
                        // mismos asignable/color/etiqueta (ej. se está reaplicando "Receso"
                        // tras revertir uno de los días a mano), esa fila no se contaba,
                        // aunque sí quedó actualizada. $idsAReplicar ya es el conteo real de
                        // filas que la operación tocó.
                        $replicadas = $idsAReplicar->count();
                    }

                    // Un día puede quedar sin ninguna franja con esta hora si esa franja se
                    // eliminó de verdad (botón "Eliminar", no el toggle "No asignable" — ese
                    // ahora solo desmarca, ver desmarcarFranjaNoAsignable) y por eso nunca
                    // aparecería en $candidatas. Sin este paso, "aplicar a todos los días" no
                    // podría recuperar un día así. Se crea una franja nueva para cada día que
                    // ya tiene grilla en este esquema (mismo criterio que "no toca otros
                    // días": si el esquema no opera ese día, tampoco se inventa una franja
                    // ahí) pero le falta esta hora puntual.
                    if ($marcandoNoAsignable) {
                        $diasDelEsquema = FranjaHoraria::where('id', '<>', $franja->id)
                            ->when(
                                $franja->id_esquema !== null,
                                fn ($q) => $q->where('id_esquema', $franja->id_esquema),
                                fn ($q) => $q->where('id_anio_escolar', $franja->id_anio_escolar)
                            )
                            ->distinct()
                            ->pluck('id_dia_semana');

                        $diasFaltantes = $diasDelEsquema
                            ->diff($candidatas->pluck('id_dia_semana'))
                            ->diff([$franja->id_dia_semana]);

                        foreach ($diasFaltantes as $dia) {
                            $franjasDelDia = FranjaHoraria::where('id_dia_semana', $dia)
                                ->when(
                                    $franja->id_esquema !== null,
                                    fn ($q) => $q->where('id_esquema', $franja->id_esquema),
                                    fn ($q) => $q->where('id_anio_escolar', $franja->id_anio_escolar)
                                )
                                ->orderBy('orden')
                                ->get();

                            $hayCruceDia = $this->existeCruceHorario(
                                FranjaHoraria::whereIn('id', $franjasDelDia->pluck('id')),
                                $nuevaHoraInicio,
                                $nuevaHoraFin
                            );

                            if ($hayCruceDia) {
                                continue;
                            }

                            // orden refleja el orden CRONOLÓGICO del día, no "lo último que
                            // se agregó" — insertar siempre al final (max+1) descolocaba una
                            // franja recreada a mitad del día (ej. un receso al mediodía)
                            // hasta después de las franjas de la tarde. La nueva posición es
                            // el orden de la última franja anterior a esta hora, +1 (no un
                            // conteo de cuántas franjas hay antes: eso rompe si el orden del
                            // día tiene huecos). Se recorren +1 las que quedan después, para
                            // abrirle campo sin chocar con uq_franja_horaria_esquema
                            // (id_esquema/id_dia_semana/orden).
                            $ordenAnterior = (int) ($franjasDelDia->filter(fn (FranjaHoraria $f) => $f->hora_inicio < $nuevaHoraInicio)->max('orden') ?? 0);
                            $ordenNueva = $ordenAnterior + 1;
                            $aCorrer = $franjasDelDia->filter(fn (FranjaHoraria $f) => $f->orden >= $ordenNueva);

                            if ($aCorrer->isNotEmpty()) {
                                // Dos pasadas a valores temporales negativos, mismo motivo
                                // que actualizarOrdenFranjasHorarias(): no se puede pisar el
                                // índice único mientras otras filas del grupo aún tienen su
                                // orden viejo.
                                foreach ($aCorrer as $f) {
                                    FranjaHoraria::where('id', $f->id)->update(['orden' => -$f->id]);
                                }
                                foreach ($aCorrer as $f) {
                                    FranjaHoraria::where('id', $f->id)->update(['orden' => $f->orden + 1]);
                                }
                            }

                            $ordenSiguiente = $ordenNueva;

                            FranjaHoraria::create([
                                'id_anio_escolar' => $franja->id_anio_escolar,
                                'id_esquema' => $franja->id_esquema,
                                'id_dia_semana' => $dia,
                                'hora_inicio' => $nuevaHoraInicio,
                                'hora_fin' => $nuevaHoraFin,
                                'orden' => $ordenSiguiente,
                                'asignable' => false,
                                'color' => $camposReplicar['color'] ?? $franja->color,
                                'etiqueta' => $camposReplicar['etiqueta'] ?? $franja->etiqueta,
                                'id_franja_pivote' => $franja->id,
                            ]);

                            $replicadas++;
                        }
                    }
                }
            }

            $mensaje = 'Horario de la franja actualizado correctamente.';
            if ($replicadas > 0) {
                $mensaje .= " Se aplicó también a {$replicadas} franja(s) de otros días con esa misma hora.";
            }
            if ($omitidasPorClase > 0) {
                $mensaje .= " Se omitió en {$omitidasPorClase} franja(s) que ya tienen una clase asignada.";
            }
            if ($omitidasPorPivote > 0) {
                $mensaje .= " Se omitió en {$omitidasPorPivote} franja(s) que ya dependen de otro horario pivote.";
            }

            return [
                'error' => false,
                'message' => $mensaje,
                'data' => $franja->fresh()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el horario de la franja');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el horario.',
                'data' => []
            ];
        }
    }

    /**
     * Desmarca una franja "no asignable": vuelve asignable=true y limpia color/etiqueta/
     * id_franja_pivote, pero conserva la franja (hora_inicio/hora_fin intactos) como un
     * bloque normal — no la elimina. No se puede desmarcar si ya tiene una clase asignada
     * (no debería pasar en una franja no asignable, pero puede ocurrir con datos de antes
     * de esa validación).
     */
    private function desmarcarFranjaNoAsignable(FranjaHoraria $franja): array
    {
        if ($franja->horarioClase()->exists()) {
            return [
                'error' => true,
                'message' => 'Esta franja ya tiene una clase asignada; elimínala o reprográmala antes de deshabilitar el receso/almuerzo.',
                'data' => []
            ];
        }

        $franja->update(['asignable' => true, 'color' => null, 'etiqueta' => null, 'id_franja_pivote' => null]);

        return [
            'error' => false,
            'message' => 'Franja desmarcada como no asignable.',
            'data' => $franja->fresh()
        ];
    }

    /**
     * Inverso de aplicarTodosDias en actualizarHorarioFranja(): desmarca (no elimina — ver
     * desmarcarFranjaNoAsignable) todas las franjas que dependen de $id como pivote
     * (id_franja_pivote = $id) — la franja $id (la principal) no se toca, queda tal como está.
     */
    public function quitarNoAsignableDeOtrosDias(int $id): array
    {
        try {
            $franja = FranjaHoraria::find($id);

            if (!$franja) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria no existe.',
                    'data' => []
                ];
            }

            $candidatas = $franja->dependientes()->get();

            $conClaseAsignada = $candidatas->filter(fn (FranjaHoraria $f) => $f->horarioClase()->exists());
            $omitidasPorClase = $conClaseAsignada->count();
            $idsConClase = $conClaseAsignada->pluck('id');

            $idsARevertir = $candidatas->pluck('id')->diff($idsConClase);
            $revertidas = $idsARevertir->count();

            if ($idsARevertir->isNotEmpty()) {
                FranjaHoraria::whereIn('id', $idsARevertir)->update(['asignable' => true, 'color' => null, 'etiqueta' => null, 'id_franja_pivote' => null]);
            }

            $mensaje = $revertidas > 0
                ? "Se volvieron a marcar como asignables {$revertidas} franja(s) de otros días. \"{$franja->etiqueta}\" se mantuvo en esta franja."
                : 'No había otras franjas no asignables con esa misma hora para revertir.';

            if ($omitidasPorClase > 0) {
                $mensaje .= " Se omitió en {$omitidasPorClase} franja(s) que ya tienen una clase asignada.";
            }

            return [
                'error' => false,
                'message' => $mensaje,
                'data' => ['revertidas' => $revertidas]
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar el horario de la franja');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el horario.',
                'data' => []
            ];
        }
    }
}

<?php

namespace App\Services\encuestas;

use App\Mail\EncuestaConsolidadoMail;
use App\Models\Encuestas\Encuesta;
use App\Models\Encuestas\EncuestaOpcionPregunta;
use App\Models\Encuestas\EncuestaPregunta;
use App\Models\Encuestas\EncuestaRespuesta;
use App\Models\Encuestas\EncuestaSalon;
use App\Models\Evaluaciones\EvaluacionTipoPregunta;
use App\Models\Reservas\Reservas;
use App\Models\Reservas\Salones;
use App\Pdf\Encuestas\EncuestaConsolidadoPdfService;
use App\Services\branding\MarcaDominioService;
use App\Services\MailService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EncuestasServices
{
    // ─── Catálogo de tipos de pregunta (reusa el de Evaluaciones) ────

    public function listarTiposPregunta(): array
    {
        try {
            $data = EvaluacionTipoPregunta::with('opciones')->orderBy('nombre')->get();

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Encuestas (CRUD + preguntas/opciones anidadas) ──────────────

    public function listar(array $filtros): array
    {
        try {
            // salones_count + respuestas_max_created_at: tarjetas y encuesta por defecto
            // (la del envío más reciente) de la pestaña Resultados.
            $query = Encuesta::withCount('preguntas', 'respuestas', 'salones')
                ->withMax('respuestas', 'created_at');

            if (! empty($filtros['s'])) {
                $busqueda = $filtros['s'];
                $query->where(function ($q) use ($busqueda) {
                    $q->where('titulo', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%");
                });
            }

            if (isset($filtros['activo']) && $filtros['activo'] !== '') {
                $query->where('activo', $filtros['activo']);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $filtros['per-page'] ?? 15;
            $data = $query->paginate($perPage);

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function obtenerPorId(int $id): array
    {
        try {
            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])
                ->withCount('respuestas')
                ->find($id);

            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            return ['error' => false, 'message' => 'ok', 'data' => $encuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $datos): array
    {
        try {
            return DB::transaction(function () use ($datos) {
                $encuesta = Encuesta::create([
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'activo' => $datos['activo'] ?? 1,
                ]);

                if (! empty($datos['preguntas'])) {
                    foreach ($datos['preguntas'] as $idx => $pregData) {
                        $pregunta = $encuesta->preguntas()->create([
                            'id_tipo_pregunta' => $pregData['id_tipo_pregunta'],
                            'texto' => $pregData['texto'],
                            'obligatoria' => $pregData['obligatoria'] ?? 1,
                            'orden' => $pregData['orden'] ?? $idx,
                        ]);

                        if (! empty($pregData['opciones'])) {
                            foreach ($pregData['opciones'] as $optIdx => $optData) {
                                $pregunta->opciones()->create([
                                    'texto' => $optData['texto'],
                                    'orden' => $optData['orden'] ?? $optIdx,
                                ]);
                            }
                        }
                    }
                }

                return ['error' => false, 'message' => 'Encuesta creada', 'data' => $encuesta->fresh(['preguntas.tipo', 'preguntas.opciones'])];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            $encuesta = Encuesta::find($id);
            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $encuesta->update([
                'titulo' => $datos['titulo'] ?? $encuesta->titulo,
                // array_key_exists: borrar la descripción llega como null y debe quedar vacía.
                'descripcion' => array_key_exists('descripcion', $datos) ? $datos['descripcion'] : $encuesta->descripcion,
                'activo' => $datos['activo'] ?? $encuesta->activo,
            ]);

            return ['error' => false, 'message' => 'Encuesta actualizada', 'data' => $encuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Copia la encuesta con sus preguntas y opciones, titulada "<título> (Copia n)" e
     * inactiva. No copia salones asignados ni respuestas. Mismo criterio que
     * EvaluacionesServices::duplicar, reusando crear().
     */
    public function duplicar(int $id): array
    {
        try {
            $original = Encuesta::with('preguntas.opciones')->find($id);
            if (! $original) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            // Copiar una copia sigue la numeración de la original, no "(Copia 1) (Copia 1)".
            $base = preg_replace('/ \(Copia \d+\)$/', '', $original->titulo);
            $n = 1;
            do {
                $sufijo = " (Copia $n)";
                $titulo = mb_substr($base, 0, 255 - mb_strlen($sufijo)).$sufijo;
                $n++;
            } while (Encuesta::where('titulo', $titulo)->exists());

            $resultado = $this->crear([
                'titulo' => $titulo,
                'descripcion' => $original->descripcion,
                'activo' => 0,
                'preguntas' => $original->preguntas->map(fn (EncuestaPregunta $p) => [
                    'id_tipo_pregunta' => $p->id_tipo_pregunta,
                    'texto' => $p->texto,
                    'obligatoria' => $p->obligatoria,
                    'orden' => $p->orden,
                    'opciones' => $p->opciones->map(fn (EncuestaOpcionPregunta $o) => [
                        'texto' => $o->texto,
                        'orden' => $o->orden,
                    ])->all(),
                ])->all(),
            ]);

            if (! $resultado['error']) {
                $resultado['message'] = 'Encuesta duplicada';
            }

            return $resultado;
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $encuesta = Encuesta::find($id);
            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $encuesta->delete();

            return ['error' => false, 'message' => 'Encuesta eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function toggleActivo(int $id): array
    {
        try {
            $encuesta = Encuesta::find($id);
            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $encuesta->update(['activo' => $encuesta->activo ? 0 : 1]);

            return ['error' => false, 'message' => $encuesta->activo ? 'Encuesta activada' : 'Encuesta desactivada', 'data' => ['activo' => $encuesta->activo]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Preguntas ────────────────────────────────────────────────

    public function crearPregunta(int $idEncuesta, array $datos): array
    {
        try {
            $encuesta = Encuesta::find($idEncuesta);
            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $pregunta = $encuesta->preguntas()->create([
                'id_tipo_pregunta' => $datos['id_tipo_pregunta'],
                'texto' => $datos['texto'],
                'obligatoria' => $datos['obligatoria'] ?? 1,
                'orden' => $datos['orden'] ?? $encuesta->preguntas()->count(),
            ]);

            if (! empty($datos['opciones'])) {
                foreach ($datos['opciones'] as $idx => $optData) {
                    $pregunta->opciones()->create([
                        'texto' => $optData['texto'],
                        'orden' => $optData['orden'] ?? $idx,
                    ]);
                }
            }

            return ['error' => false, 'message' => 'Pregunta creada', 'data' => $pregunta->fresh(['tipo', 'opciones'])];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarPregunta(int $idPregunta, array $datos): array
    {
        try {
            $pregunta = EncuestaPregunta::find($idPregunta);
            if (! $pregunta) {
                return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];
            }

            $pregunta->update([
                'id_tipo_pregunta' => $datos['id_tipo_pregunta'] ?? $pregunta->id_tipo_pregunta,
                'texto' => $datos['texto'] ?? $pregunta->texto,
                'obligatoria' => $datos['obligatoria'] ?? $pregunta->obligatoria,
                'orden' => $datos['orden'] ?? $pregunta->orden,
            ]);

            return ['error' => false, 'message' => 'Pregunta actualizada', 'data' => $pregunta->fresh(['tipo', 'opciones'])];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarPregunta(int $idPregunta): array
    {
        try {
            $pregunta = EncuestaPregunta::find($idPregunta);
            if (! $pregunta) {
                return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];
            }

            $pregunta->delete();

            return ['error' => false, 'message' => 'Pregunta eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * `$ids` = todas las preguntas de la encuesta en el orden nuevo (el índice pasa a ser
     * `orden`). Debe traer exactamente las preguntas actuales: una lista vieja (otra pestaña
     * agregó o borró una pregunta) se rechaza en vez de dejar órdenes repetidos.
     */
    public function reordenarPreguntas(int $idEncuesta, array $ids): array
    {
        try {
            $actuales = EncuestaPregunta::where('id_encuesta', $idEncuesta)->pluck('id')->all();
            if (count($ids) !== count($actuales) || array_diff($actuales, $ids)) {
                return ['error' => true, 'message' => 'Las preguntas de la encuesta cambiaron. Recarga para reordenarlas.', 'status' => 422];
            }

            DB::transaction(function () use ($ids) {
                foreach (array_values($ids) as $orden => $id) {
                    EncuestaPregunta::where('id', $id)->update(['orden' => $orden]);
                }
            });

            return ['error' => false, 'message' => 'Orden guardado'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Opciones de pregunta ─────────────────────────────────────

    public function crearOpcion(int $idPregunta, array $datos): array
    {
        try {
            $pregunta = EncuestaPregunta::find($idPregunta);
            if (! $pregunta) {
                return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];
            }

            $opcion = $pregunta->opciones()->create([
                'texto' => $datos['texto'],
                'orden' => $datos['orden'] ?? $pregunta->opciones()->count(),
            ]);

            return ['error' => false, 'message' => 'Opción creada', 'data' => $opcion];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarOpcion(int $idOpcion, array $datos): array
    {
        try {
            $opcion = EncuestaOpcionPregunta::find($idOpcion);
            if (! $opcion) {
                return ['error' => true, 'message' => 'Opción no encontrada', 'status' => 404];
            }

            $opcion->update($datos);

            return ['error' => false, 'message' => 'Opción actualizada', 'data' => $opcion];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarOpcion(int $idOpcion): array
    {
        try {
            $opcion = EncuestaOpcionPregunta::find($idOpcion);
            if (! $opcion) {
                return ['error' => true, 'message' => 'Opción no encontrada', 'status' => 404];
            }

            $opcion->delete();

            return ['error' => false, 'message' => 'Opción eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Salones (asignación de encuesta activa + token del QR) ─────

    /** Trae/crea (si aún no existe) la fila `encuestas_salon` de un salón, con su token público. */
    private function resolverFilaSalon(int $idSalon): EncuestaSalon
    {
        $fila = EncuestaSalon::where('id_salon', $idSalon)->first();
        if ($fila) {
            return $fila;
        }

        return EncuestaSalon::create([
            'id_salon' => $idSalon,
            'id_encuesta' => null,
            'token_publico' => Str::random(32),
        ]);
    }

    public function listarSalones(): array
    {
        try {
            $salones = Salones::activo()->orderBy('nombre')->get(['id', 'nombre']);

            $data = $salones->map(function (Salones $salon) {
                $fila = $this->resolverFilaSalon($salon->id);
                $fila->loadMissing('encuesta:id,titulo,activo');

                return [
                    'id_salon' => $salon->id,
                    'nombre' => $salon->nombre,
                    'id_encuesta' => $fila->id_encuesta,
                    'encuesta' => $fila->encuesta,
                    'token_publico' => $fila->token_publico,
                ];
            });

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function asignarEncuestaSalon(int $idSalon, ?int $idEncuesta): array
    {
        try {
            $salon = Salones::find($idSalon);
            if (! $salon) {
                return ['error' => true, 'message' => 'Salón no encontrado', 'status' => 404];
            }

            if ($idEncuesta !== null && ! Encuesta::where('id', $idEncuesta)->exists()) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $fila = $this->resolverFilaSalon($idSalon);
            $fila->update(['id_encuesta' => $idEncuesta]);

            return ['error' => false, 'message' => 'Encuesta del salón actualizada', 'data' => $fila->fresh('encuesta')];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Asigna la encuesta activa de varios salones en una sola transacción — usado por el
     * botón "Guardar cambios" de la pestaña Salones y QR, que junta todas las selecciones
     * hechas en pantalla y las manda en una sola solicitud en vez de una por cada select.
     * `$asignaciones` es un array de ['id_salon' => int, 'id_encuesta' => ?int].
     */
    public function asignarEncuestasLote(array $asignaciones): array
    {
        try {
            $idsSalon = array_column($asignaciones, 'id_salon');
            $idsEncuesta = array_values(array_filter(array_column($asignaciones, 'id_encuesta'), fn ($id) => $id !== null));

            $salonesExistentes = Salones::whereIn('id', $idsSalon)->pluck('id')->all();
            $faltanSalones = array_diff($idsSalon, $salonesExistentes);
            if (! empty($faltanSalones)) {
                return ['error' => true, 'message' => 'Salón no encontrado: '.implode(', ', $faltanSalones), 'status' => 404];
            }

            if (! empty($idsEncuesta)) {
                $encuestasExistentes = Encuesta::whereIn('id', $idsEncuesta)->pluck('id')->all();
                $faltanEncuestas = array_diff($idsEncuesta, $encuestasExistentes);
                if (! empty($faltanEncuestas)) {
                    return ['error' => true, 'message' => 'Encuesta no encontrada: '.implode(', ', $faltanEncuestas), 'status' => 404];
                }
            }

            DB::transaction(function () use ($asignaciones) {
                foreach ($asignaciones as $asignacion) {
                    $fila = $this->resolverFilaSalon($asignacion['id_salon']);
                    $fila->update(['id_encuesta' => $asignacion['id_encuesta']]);
                }
            });

            return ['error' => false, 'message' => 'Encuestas de los salones actualizadas'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Rota el token público del salón (invalida cualquier QR ya impreso con el anterior). */
    public function regenerarTokenSalon(int $idSalon): array
    {
        try {
            $fila = $this->resolverFilaSalon($idSalon);
            $fila->update(['token_publico' => Str::random(32)]);

            return ['error' => false, 'message' => 'Token regenerado', 'data' => $fila];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Flujo público (sin auth, anónimo) ───────────────────────────

    /**
     * Una pregunta de opciones sin ninguna opción (p. ej. creada con un tipo sin opciones
     * por defecto y nunca completada) no se puede responder: se oculta en el flujo público
     * y no cuenta como obligatoria, para no bloquear el envío de toda la encuesta.
     */
    private function esRespondible(EncuestaPregunta $pregunta): bool
    {
        return $pregunta->tipo?->slug === 'texto_libre' || $pregunta->opciones->isNotEmpty();
    }

    public function obtenerEncuestaPublica(string $token): array
    {
        try {
            $filaSalon = EncuestaSalon::where('token_publico', $token)->first();
            if (! $filaSalon || ! $filaSalon->id_encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])
                ->where('id', $filaSalon->id_encuesta)
                ->where('activo', 1)
                ->first();

            if (! $encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            // Nombre del salón para que el visitante vea dónde está respondiendo.
            $encuesta->setAttribute('salon', $filaSalon->salon?->nombre);
            $encuesta->setRelation('preguntas', $encuesta->preguntas->filter(fn ($p) => $this->esRespondible($p))->values());

            return ['error' => false, 'message' => 'ok', 'data' => $encuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reservas vigentes de hoy para el salón de este token, agrupadas por título+
     * responsable (mismo criterio que `agruparPorSalon.utils.ts` en el frontend admin,
     * pero por `id_user` en vez de nombre-string) — el visitante elige una antes de poder
     * responder. `id_reserva` de cada grupo es el `id` de su primera franja horaria, el
     * mismo que se guarda luego en `encuestas_respuestas.id_reserva`.
     */
    public function reservasHoySalon(string $token): array
    {
        try {
            $filaSalon = EncuestaSalon::where('token_publico', $token)->first();
            if (! $filaSalon) {
                return ['error' => true, 'message' => 'Salón no encontrado', 'status' => 404];
            }

            $reservas = Reservas::with(['hora:id,horas', 'usuario:id_user,nombre,apellido'])
                ->where('id_salon', $filaSalon->id_salon)
                ->whereDate('fecha_reserva', Carbon::today())
                ->where('activo', 1)
                ->whereNull('fecha_cancelado')
                ->orderBy('hora_reserva')
                ->get();

            $grupos = $reservas
                ->groupBy(fn (Reservas $r) => $r->titulo.'|'.$r->id_user)
                ->map(function ($rs) {
                    $primero = $rs->first();
                    $responsable = trim(($primero->usuario->nombre ?? '').' '.($primero->usuario->apellido ?? ''));

                    return [
                        'id_reserva' => $primero->id,
                        'titulo' => $primero->titulo,
                        'responsable' => $responsable ?: '—',
                        'horas' => $rs->pluck('hora.horas')->filter()->values(),
                    ];
                })
                ->values();

            return ['error' => false, 'message' => 'ok', 'data' => $grupos];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function responderPublica(string $token, array $respuestas, ?int $idReserva = null): array
    {
        try {
            $filaSalon = EncuestaSalon::where('token_publico', $token)->first();
            if (! $filaSalon || ! $filaSalon->id_encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])
                ->where('id', $filaSalon->id_encuesta)
                ->where('activo', 1)
                ->first();

            if (! $encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            // Defensa además del gate del frontend (que ya obliga a elegir una reserva
            // cuando hay disponibles): si viene un id_reserva, debe ser una reserva real
            // de ESTE salón y de HOY — evita que alguien la falsifique llamando la API
            // directo con un id de cualquier reserva de cualquier salón/fecha.
            if ($idReserva !== null) {
                $reservaValida = Reservas::where('id', $idReserva)
                    ->where('id_salon', $filaSalon->id_salon)
                    ->whereDate('fecha_reserva', Carbon::today())
                    ->exists();
                if (! $reservaValida) {
                    return ['error' => true, 'message' => 'La reserva seleccionada no es válida para este salón', 'status' => 422];
                }
            }

            $respuestasPorPregunta = collect($respuestas)->keyBy('id_pregunta');
            foreach ($encuesta->preguntas as $pregunta) {
                if (! $pregunta->obligatoria || ! $this->esRespondible($pregunta)) {
                    continue;
                }

                $resp = $respuestasPorPregunta->get($pregunta->id);
                $sinResponder = ! $resp || (empty($resp['id_opcion']) && empty(trim((string) ($resp['valor_texto'] ?? ''))));
                if ($sinResponder) {
                    return ['error' => true, 'message' => 'Falta responder una o más preguntas obligatorias', 'status' => 422];
                }
            }

            $respuesta = DB::transaction(function () use ($filaSalon, $encuesta, $respuestas, $idReserva) {
                $respuesta = EncuestaRespuesta::create([
                    'id_encuesta' => $encuesta->id,
                    'id_salon' => $filaSalon->id_salon,
                    'id_reserva' => $idReserva,
                ]);

                foreach ($respuestas as $respData) {
                    $respuesta->respuestasPreguntas()->create([
                        'id_pregunta' => $respData['id_pregunta'],
                        'id_opcion' => $respData['id_opcion'] ?? null,
                        'valor_texto' => $respData['valor_texto'] ?? null,
                    ]);
                }

                return $respuesta;
            });

            return ['error' => false, 'message' => 'Respuesta registrada', 'data' => ['id' => $respuesta->id]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Resultados ───────────────────────────────────────────────

    /** Salones que registran al menos una respuesta de esta encuesta — alimenta el filtro por salón de la pestaña Resultados. */
    public function salonesConRespuestas(int $idEncuesta): array
    {
        try {
            $data = DB::table('encuestas_respuestas as er')
                ->join('salones as s', 's.id', '=', 'er.id_salon')
                ->where('er.id_encuesta', $idEncuesta)
                ->select('s.id as id_salon', 's.nombre')
                ->distinct()
                ->orderBy('s.nombre')
                ->get();

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reservas que registran al menos una respuesta de esta encuesta — alimenta el tercer
     * filtro (por reserva puntual) de la pestaña Resultados. `$idSalon` acota a las
     * reservas de ese salón; sin él, trae las de todos los salones donde se respondió.
     */
    public function reservasConRespuestas(int $idEncuesta, ?int $idSalon = null): array
    {
        try {
            $idsReserva = EncuestaRespuesta::where('id_encuesta', $idEncuesta)
                ->when($idSalon, fn ($q) => $q->where('id_salon', $idSalon))
                ->whereNotNull('id_reserva')
                ->distinct()
                ->pluck('id_reserva');

            $data = Reservas::with('usuario:id_user,nombre,apellido')
                ->whereIn('id', $idsReserva)
                ->get()
                ->map(fn (Reservas $r) => [
                    'id_reserva' => $r->id,
                    'titulo' => $r->titulo,
                    'responsable' => trim(($r->usuario->nombre ?? '').' '.($r->usuario->apellido ?? '')) ?: '—',
                    'fecha_reserva' => $r->fecha_reserva,
                ])
                ->sortBy('fecha_reserva')
                ->values();

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Filtros de la pestaña Resultados sobre los envíos (`encuestas_respuestas`, con alias
     * `$tabla`): `id_salon`, `id_reserva` y `id_pregunta` + `id_opcion`. Este último deja
     * solo los envíos que eligieron esa opción en esa pregunta: es el filtro de "no
     * conformidades" (filtro manual, no se marca nada en la pregunta).
     *
     * @param  array{id_salon?: ?int, id_reserva?: ?int, id_pregunta?: ?int, id_opcion?: ?int}  $filtros
     */
    private function filtrarEnvios($query, array $filtros, string $tabla): void
    {
        $query->when($filtros['id_salon'] ?? null, fn ($q, $id) => $q->where("$tabla.id_salon", $id))
            ->when($filtros['id_reserva'] ?? null, fn ($q, $id) => $q->where("$tabla.id_reserva", $id))
            ->when(
                ($filtros['id_pregunta'] ?? null) && ($filtros['id_opcion'] ?? null),
                fn ($q) => $q->whereExists(fn ($sub) => $sub->from('encuestas_respuestas_pregunta as fp')
                    ->whereColumn('fp.id_respuesta', "$tabla.id")
                    ->where('fp.id_pregunta', $filtros['id_pregunta'])
                    ->where('fp.id_opcion', $filtros['id_opcion']))
            );
    }

    /**
     * Totales por pregunta. Sin filtros, junta las respuestas de todos los salones y
     * reservas donde se respondió la encuesta. `id_reserva` implica un único salón, pero
     * se aceptan ambos por separado para que el filtro de la pestaña Resultados pueda ir
     * acotando en cascada (encuesta → salón → reserva).
     *
     * @param  array{id_salon?: ?int, id_reserva?: ?int, id_pregunta?: ?int, id_opcion?: ?int}  $filtros
     */
    public function resultados(int $idEncuesta, array $filtros = []): array
    {
        try {
            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])->find($idEncuesta);
            if (! $encuesta) {
                return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];
            }

            $totalQuery = EncuestaRespuesta::where('id_encuesta', $idEncuesta);
            $this->filtrarEnvios($totalQuery, $filtros, 'encuestas_respuestas');
            $totalRespuestas = $totalQuery->count();

            // Mismo filtro que el formulario público: una pregunta de opciones sin opciones no
            // se puede responder, así que tampoco se muestra (antes salía vacía en Resultados/PDF).
            $preguntas = $encuesta->preguntas->filter(fn ($p) => $this->esRespondible($p))->values()->map(function (EncuestaPregunta $pregunta) use ($filtros) {
                $esTexto = $pregunta->tipo?->slug === 'texto_libre';

                // Join contra encuestas_respuestas: encuestas_respuestas_pregunta no tiene
                // id_salon/id_reserva propios, así que filtrar por esos campos exige pasar
                // por la respuesta dueña de cada fila.
                $baseQuery = DB::table('encuestas_respuestas_pregunta as erp')
                    ->join('encuestas_respuestas as er', 'er.id', '=', 'erp.id_respuesta')
                    ->where('erp.id_pregunta', $pregunta->id);
                $this->filtrarEnvios($baseQuery, $filtros, 'er');

                if ($esTexto) {
                    $textos = (clone $baseQuery)->whereNotNull('erp.valor_texto')->pluck('erp.valor_texto');

                    return [
                        'id_pregunta' => $pregunta->id,
                        'texto' => $pregunta->texto,
                        'tipo' => $pregunta->tipo,
                        'respuestas_texto' => $textos,
                    ];
                }

                $conteos = (clone $baseQuery)
                    ->whereNotNull('erp.id_opcion')
                    ->select('erp.id_opcion', DB::raw('count(*) as total'))
                    ->groupBy('erp.id_opcion')
                    ->pluck('total', 'id_opcion');

                $opciones = $pregunta->opciones->map(fn (EncuestaOpcionPregunta $opcion) => [
                    'id_opcion' => $opcion->id,
                    'texto' => $opcion->texto,
                    'total' => (int) ($conteos[$opcion->id] ?? 0),
                ]);

                return [
                    'id_pregunta' => $pregunta->id,
                    'texto' => $pregunta->texto,
                    'tipo' => $pregunta->tipo,
                    'opciones' => $opciones,
                ];
            });

            return ['error' => false, 'message' => 'ok', 'data' => [
                'id_encuesta' => $encuesta->id,
                'titulo' => $encuesta->titulo,
                'total_respuestas' => $totalRespuestas,
                'preguntas' => $preguntas,
            ]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Envíos uno por uno (vistas Pregunta e Individual de Resultados, y anexo del PDF).
     * El `id` y el `created_at` de `encuestas_respuestas` son el identificador de cada
     * envío: con ellos se referencia una no conformidad ("#154 del 10/11"). Siguen siendo
     * anónimos: identifican el envío, no a quien respondió.
     *
     * @param  array{id_salon?: ?int, id_reserva?: ?int, id_pregunta?: ?int, id_opcion?: ?int}  $filtros
     */
    public function envios(int $idEncuesta, array $filtros = []): array
    {
        try {
            // ponytail: sin paginar; paginar si una encuesta llega a varios miles de envíos.
            $base = DB::table('encuestas_respuestas as er')->where('er.id_encuesta', $idEncuesta);
            $this->filtrarEnvios($base, $filtros, 'er');

            $envios = (clone $base)
                ->join('salones as s', 's.id', '=', 'er.id_salon')
                ->leftJoin('reservas as r', 'r.id', '=', 'er.id_reserva')
                ->orderByDesc('er.created_at')
                ->orderByDesc('er.id')
                ->get(['er.id', 'er.created_at', 'er.id_reserva', 'r.titulo as reserva_titulo', 's.nombre as salon']);

            $respuestasPorEnvio = DB::table('encuestas_respuestas_pregunta as erp')
                ->leftJoin('encuestas_opciones_pregunta as o', 'o.id', '=', 'erp.id_opcion')
                ->whereIn('erp.id_respuesta', (clone $base)->select('er.id'))
                ->get(['erp.id_respuesta', 'erp.id_pregunta', 'erp.id_opcion', 'o.texto as opcion', 'erp.valor_texto'])
                ->groupBy('id_respuesta');

            $data = $envios->map(fn ($envio) => [
                'id' => $envio->id,
                'created_at' => $envio->created_at,
                'id_reserva' => $envio->id_reserva,
                'reserva_titulo' => $envio->reserva_titulo,
                'salon' => $envio->salon,
                'respuestas' => ($respuestasPorEnvio[$envio->id] ?? collect())
                    ->groupBy('id_pregunta')
                    ->map(fn ($filas, $idPregunta) => [
                        'id_pregunta' => (int) $idPregunta,
                        'opciones' => $filas->whereNotNull('id_opcion')
                            ->map(fn ($fila) => ['id_opcion' => $fila->id_opcion, 'texto' => $fila->opcion])
                            ->values(),
                        'valor_texto' => $filas->pluck('valor_texto')->filter()->first(),
                    ])
                    ->values(),
            ]);

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * PDF consolidado de los resultados con los mismos filtros de la pestaña Resultados
     * (por reserva es el que recibe el responsable a las 5 pm). `$correoLogo` resuelve la
     * marca/logo del PDF; null = la del usuario autenticado (descarga desde el módulo).
     *
     * @param  array{id_salon?: ?int, id_reserva?: ?int, id_pregunta?: ?int, id_opcion?: ?int}  $filtros
     * @return array{error: bool, message: string, data?: array{contenido: string, nombre: string, titulo: string}}
     */
    public function generarPdfResultados(int $idEncuesta, array $filtros = [], ?string $correoLogo = null): array
    {
        try {
            $resultados = $this->resultados($idEncuesta, $filtros);
            if ($resultados['error']) {
                return $resultados;
            }

            $envios = $this->envios($idEncuesta, $filtros);
            if ($envios['error']) {
                return $envios;
            }

            $preguntas = collect($resultados['data']['preguntas']);

            $reserva = ! empty($filtros['id_reserva'])
                ? Reservas::with(['usuario:id_user,nombre,apellido', 'salon:id,nombre'])->find($filtros['id_reserva'])
                : null;

            $salon = $reserva?->salon?->nombre
                ?? (! empty($filtros['id_salon']) ? Salones::find($filtros['id_salon'])?->nombre : null)
                ?? 'Todos';

            $ficha = ['Encuesta' => $resultados['data']['titulo'], 'Salón' => $salon];
            if ($reserva) {
                $ficha['Reserva'] = $reserva->titulo;
                $ficha['Responsable'] = trim(($reserva->usuario->nombre ?? '').' '.($reserva->usuario->apellido ?? '')) ?: '—';
                $ficha['Fecha de la reserva'] = Carbon::parse($reserva->fecha_reserva)->locale('es')->translatedFormat('j \d\e F \d\e Y');
            }

            if (! empty($filtros['id_pregunta']) && ! empty($filtros['id_opcion'])) {
                $pregunta = $preguntas->firstWhere('id_pregunta', (int) $filtros['id_pregunta']);
                $opcion = collect($pregunta['opciones'] ?? [])->firstWhere('id_opcion', (int) $filtros['id_opcion']);
                if ($pregunta && $opcion) {
                    $ficha['Filtro'] = $pregunta['texto'].' = '.$opcion['texto'];
                }
            }

            $ficha['Total de respuestas'] = (string) $resultados['data']['total_respuestas'];

            // Logo de la marca por dominio del correo (el del responsable en el envío de las
            // 5 pm; el del usuario autenticado en la descarga). Sin marca cae al de respaldo.
            $contenido = app(EncuestaConsolidadoPdfService::class)->generate(
                $preguntas->all(),
                $envios['data']->all(),
                $ficha,
                app(MarcaDominioService::class)->rutaLocalLogo($correoLogo),
            );

            $nombre = 'encuesta-'.$idEncuesta.($reserva ? '-reserva-'.$reserva->id : '').'.pdf';

            return ['error' => false, 'message' => 'ok', 'data' => [
                'contenido' => $contenido,
                'nombre' => $nombre,
                'titulo' => $resultados['data']['titulo'],
            ]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Comando `encuestas:enviar-consolidado` (17:00): por cada reserva de hoy con
     * respuestas, envía al responsable el PDF consolidado de cada encuesta respondida. Las
     * reservas sin respuestas no reciben correo. `id_reserva` de un envío apunta a la
     * primera franja de la reserva (ver reservasHoySalon), que es de hoy.
     */
    public function enviarConsolidadosDelDia(): array
    {
        try {
            $idsReservasHoy = Reservas::whereDate('fecha_reserva', Carbon::today())
                ->where('activo', 1)
                ->whereNull('fecha_cancelado')
                ->pluck('id');

            $pares = EncuestaRespuesta::whereIn('id_reserva', $idsReservasHoy)
                ->select('id_encuesta', 'id_reserva')
                ->distinct()
                ->get();

            $enviados = 0;
            $omitidos = 0;

            foreach ($pares as $par) {
                $reserva = Reservas::with(['usuario:id_user,nombre,apellido,correo', 'salon:id,nombre'])->find($par->id_reserva);
                $correo = $reserva?->usuario?->correo;

                if (! $correo) {
                    $omitidos++;

                    continue;
                }

                $pdf = $this->generarPdfResultados($par->id_encuesta, ['id_reserva' => $par->id_reserva], $correo);
                if ($pdf['error']) {
                    Log::warning('Consolidado de encuesta no generado', ['id_reserva' => $par->id_reserva, 'error' => $pdf['message']]);
                    $omitidos++;

                    continue;
                }

                $enviado = app(MailService::class)->send($correo, new EncuestaConsolidadoMail([
                    'nombre' => trim($reserva->usuario->nombre.' '.$reserva->usuario->apellido),
                    'reserva' => $reserva->titulo,
                    'salon' => $reserva->salon?->nombre ?? '',
                    'encuesta' => $pdf['data']['titulo'],
                    'fecha' => Carbon::parse($reserva->fecha_reserva)->locale('es')->translatedFormat('l j \d\e F \d\e Y'),
                ], $pdf['data']['contenido'], $pdf['data']['nombre']));

                $enviado ? $enviados++ : $omitidos++;
            }

            return ['error' => false, 'message' => "Consolidados de encuestas enviados: {$enviados}, omitidos: {$omitidos}.", 'data' => compact('enviados', 'omitidos')];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

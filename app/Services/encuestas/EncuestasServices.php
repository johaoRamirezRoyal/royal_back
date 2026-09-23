<?php

namespace App\Services\encuestas;

use App\Models\Encuestas\Encuesta;
use App\Models\Encuestas\EncuestaOpcionPregunta;
use App\Models\Encuestas\EncuestaPregunta;
use App\Models\Encuestas\EncuestaRespuesta;
use App\Models\Encuestas\EncuestaSalon;
use App\Models\Evaluaciones\EvaluacionTipoPregunta;
use App\Models\Reservas\Reservas;
use App\Models\Reservas\Salones;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            $query = Encuesta::withCount('preguntas', 'respuestas');

            if (!empty($filtros['s'])) {
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

            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

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

                if (!empty($datos['preguntas'])) {
                    foreach ($datos['preguntas'] as $idx => $pregData) {
                        $pregunta = $encuesta->preguntas()->create([
                            'id_tipo_pregunta' => $pregData['id_tipo_pregunta'],
                            'texto' => $pregData['texto'],
                            'obligatoria' => $pregData['obligatoria'] ?? 1,
                            'orden' => $pregData['orden'] ?? $idx,
                        ]);

                        if (!empty($pregData['opciones'])) {
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
            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

            $encuesta->update([
                'titulo' => $datos['titulo'] ?? $encuesta->titulo,
                'descripcion' => $datos['descripcion'] ?? $encuesta->descripcion,
                'activo' => $datos['activo'] ?? $encuesta->activo,
            ]);

            return ['error' => false, 'message' => 'Encuesta actualizada', 'data' => $encuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $encuesta = Encuesta::find($id);
            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

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
            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

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
            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

            $pregunta = $encuesta->preguntas()->create([
                'id_tipo_pregunta' => $datos['id_tipo_pregunta'],
                'texto' => $datos['texto'],
                'obligatoria' => $datos['obligatoria'] ?? 1,
                'orden' => $datos['orden'] ?? $encuesta->preguntas()->count(),
            ]);

            if (!empty($datos['opciones'])) {
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
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

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
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

            $pregunta->delete();
            return ['error' => false, 'message' => 'Pregunta eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Opciones de pregunta ─────────────────────────────────────

    public function crearOpcion(int $idPregunta, array $datos): array
    {
        try {
            $pregunta = EncuestaPregunta::find($idPregunta);
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

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
            if (!$opcion) return ['error' => true, 'message' => 'Opción no encontrada', 'status' => 404];

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
            if (!$opcion) return ['error' => true, 'message' => 'Opción no encontrada', 'status' => 404];

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
        if ($fila) return $fila;

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
            if (!$salon) return ['error' => true, 'message' => 'Salón no encontrado', 'status' => 404];

            if ($idEncuesta !== null && !Encuesta::where('id', $idEncuesta)->exists()) {
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
            if (!empty($faltanSalones)) {
                return ['error' => true, 'message' => 'Salón no encontrado: ' . implode(', ', $faltanSalones), 'status' => 404];
            }

            if (!empty($idsEncuesta)) {
                $encuestasExistentes = Encuesta::whereIn('id', $idsEncuesta)->pluck('id')->all();
                $faltanEncuestas = array_diff($idsEncuesta, $encuestasExistentes);
                if (!empty($faltanEncuestas)) {
                    return ['error' => true, 'message' => 'Encuesta no encontrada: ' . implode(', ', $faltanEncuestas), 'status' => 404];
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

    public function obtenerEncuestaPublica(string $token): array
    {
        try {
            $filaSalon = EncuestaSalon::where('token_publico', $token)->first();
            if (!$filaSalon || !$filaSalon->id_encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])
                ->where('id', $filaSalon->id_encuesta)
                ->where('activo', 1)
                ->first();

            if (!$encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

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
            if (!$filaSalon) {
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
                ->groupBy(fn (Reservas $r) => $r->titulo . '|' . $r->id_user)
                ->map(function ($rs) {
                    $primero = $rs->first();
                    $responsable = trim(($primero->usuario->nombre ?? '') . ' ' . ($primero->usuario->apellido ?? ''));

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
            if (!$filaSalon || !$filaSalon->id_encuesta) {
                return ['error' => true, 'message' => 'No hay una encuesta activa para este salón', 'status' => 404];
            }

            $encuesta = Encuesta::with('preguntas')
                ->where('id', $filaSalon->id_encuesta)
                ->where('activo', 1)
                ->first();

            if (!$encuesta) {
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
                if (!$reservaValida) {
                    return ['error' => true, 'message' => 'La reserva seleccionada no es válida para este salón', 'status' => 422];
                }
            }

            $respuestasPorPregunta = collect($respuestas)->keyBy('id_pregunta');
            foreach ($encuesta->preguntas as $pregunta) {
                if (!$pregunta->obligatoria) continue;

                $resp = $respuestasPorPregunta->get($pregunta->id);
                $sinResponder = !$resp || (empty($resp['id_opcion']) && empty(trim((string) ($resp['valor_texto'] ?? ''))));
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
                    'responsable' => trim(($r->usuario->nombre ?? '') . ' ' . ($r->usuario->apellido ?? '')) ?: '—',
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
     * `$idSalon`/`$idReserva` filtran las respuestas — sin ellos, agrega las de todos los
     * salones/reservas donde se respondió la encuesta. `$idReserva` implica un único salón
     * (una reserva pertenece a uno solo), pero se aceptan ambos por separado para que el
     * filtro de la pestaña Resultados pueda ir acotando en cascada (encuesta → salón →
     * reserva) sin que el frontend tenga que recalcular el salón de la reserva elegida.
     */
    public function resultados(int $idEncuesta, ?int $idSalon = null, ?int $idReserva = null): array
    {
        try {
            $encuesta = Encuesta::with(['preguntas.tipo', 'preguntas.opciones'])->find($idEncuesta);
            if (!$encuesta) return ['error' => true, 'message' => 'Encuesta no encontrada', 'status' => 404];

            $totalRespuestas = EncuestaRespuesta::where('id_encuesta', $idEncuesta)
                ->when($idSalon, fn ($q) => $q->where('id_salon', $idSalon))
                ->when($idReserva, fn ($q) => $q->where('id_reserva', $idReserva))
                ->count();

            $preguntas = $encuesta->preguntas->map(function (EncuestaPregunta $pregunta) use ($idSalon, $idReserva) {
                $esTexto = $pregunta->tipo?->slug === 'texto_libre';

                // Join contra encuestas_respuestas: encuestas_respuestas_pregunta no tiene
                // id_salon/id_reserva propios, así que filtrar por esos campos exige pasar
                // por la respuesta dueña de cada fila.
                $baseQuery = DB::table('encuestas_respuestas_pregunta as erp')
                    ->join('encuestas_respuestas as er', 'er.id', '=', 'erp.id_respuesta')
                    ->where('erp.id_pregunta', $pregunta->id)
                    ->when($idSalon, fn ($q) => $q->where('er.id_salon', $idSalon))
                    ->when($idReserva, fn ($q) => $q->where('er.id_reserva', $idReserva));

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
}

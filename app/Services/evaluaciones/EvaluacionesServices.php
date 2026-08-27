<?php

namespace App\Services\evaluaciones;

use App\Models\Evaluaciones\Evaluacion;
use App\Models\Evaluaciones\EvaluacionNivel;
use App\Models\Evaluaciones\EvaluacionOpcionPregunta;
use App\Models\Evaluaciones\EvaluacionPregunta;
use App\Models\Evaluaciones\EvaluacionRespuestaEvaluacion;
use App\Models\Evaluaciones\EvaluacionRespuestaPregunta;
use App\Models\Evaluaciones\EvaluacionSeccion;
use App\Models\Evaluaciones\EvaluacionServicio;
use App\Models\Evaluaciones\EvaluacionTipoPregunta;
use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\DB;

class EvaluacionesServices
{
    // Coordinadores (perfil 26) solo pueden evaluar dentro de su propio nivel
    // (usuarios.id_nivel); el resto de perfiles con acceso administrativo al
    // módulo (Super Admin, Gestión Humana, Administrador, etc.) no tiene esa
    // restricción — ver AGENTS.md, sección "Evaluaciones".
    private const PERFIL_COORDINADOR = 26;

    // ─── Catálogo de servicios ───────────────────────────────────

    public function listarServicios(): array
    {
        try {
            $data = EvaluacionServicio::where('activo', 1)->orderBy('nombre')->get();
            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearServicio(array $datos): array
    {
        try {
            $servicio = EvaluacionServicio::create($datos);
            return ['error' => false, 'message' => 'Servicio creado', 'data' => $servicio];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarServicio(int $id, array $datos): array
    {
        try {
            $servicio = EvaluacionServicio::find($id);
            if (!$servicio) return ['error' => true, 'message' => 'Servicio no encontrado', 'status' => 404];

            $servicio->update($datos);
            return ['error' => false, 'message' => 'Servicio actualizado', 'data' => $servicio];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarServicio(int $id): array
    {
        try {
            $servicio = EvaluacionServicio::find($id);
            if (!$servicio) return ['error' => true, 'message' => 'Servicio no encontrado', 'status' => 404];

            $servicio->update(['activo' => 0]);
            return ['error' => false, 'message' => 'Servicio desactivado'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Catálogo de tipos de pregunta ──────────────────────────

    public function listarTiposPregunta(): array
    {
        try {
            $data = EvaluacionTipoPregunta::orderBy('nombre')->get();
            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Evaluaciones (CRUD + estructura completa) ───────────────

    public function listar(array $filtros): array
    {
        try {
            $query = Evaluacion::with(['servicio', 'niveles', 'perfiles', 'secciones'])
                ->withCount('respuestas');

            if (!empty($filtros['id_servicio'])) {
                $query->where('id_servicio', $filtros['id_servicio']);
            }
            if (isset($filtros['activo'])) {
                $query->where('activo', $filtros['activo']);
            }
            if (!empty($filtros['s'])) {
                $busqueda = $filtros['s'];
                $query->where(function ($q) use ($busqueda) {
                    $q->where('titulo', 'like', "%{$busqueda}%")
                      ->orWhere('descripcion', 'like', "%{$busqueda}%");
                });
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
            $evaluacion = Evaluacion::with([
                'servicio',
                'niveles',
                'perfiles',
                'secciones.preguntas.tipo',
                'secciones.preguntas.opciones',
            ])->withCount('respuestas')->find($id);

            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            return ['error' => false, 'message' => 'ok', 'data' => $evaluacion];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $datos): array
    {
        try {
            return DB::transaction(function () use ($datos) {
                $evaluacion = Evaluacion::create([
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'id_servicio' => $datos['id_servicio'],
                    'id_user' => $datos['id_user'],
                    'activo' => $datos['activo'] ?? 1,
                    'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                    'fecha_fin' => $datos['fecha_fin'] ?? null,
                ]);

                if (!empty($datos['niveles'])) {
                    $evaluacion->niveles()->sync($datos['niveles']);
                }

                if (!empty($datos['perfiles'])) {
                    $evaluacion->perfiles()->sync($datos['perfiles']);
                }

                if (!empty($datos['secciones'])) {
                    foreach ($datos['secciones'] as $idx => $secData) {
                        $seccion = $evaluacion->secciones()->create([
                            'titulo' => $secData['titulo'],
                            'descripcion' => $secData['descripcion'] ?? null,
                            'porcentaje' => $secData['porcentaje'] ?? 0,
                            'orden' => $secData['orden'] ?? $idx,
                            'activo' => $secData['activo'] ?? 1,
                        ]);

                        if (!empty($secData['preguntas'])) {
                            foreach ($secData['preguntas'] as $pregIdx => $pregData) {
                                $pregunta = $seccion->preguntas()->create([
                                    'id_tipo_pregunta' => $pregData['id_tipo_pregunta'],
                                    'texto' => $pregData['texto'],
                                    'obligatoria' => $pregData['obligatoria'] ?? 1,
                                    'permite_comentario' => $pregData['permite_comentario'] ?? 0,
                                    'orden' => $pregData['orden'] ?? $pregIdx,
                                ]);

                                if (!empty($pregData['opciones'])) {
                                    foreach ($pregData['opciones'] as $optIdx => $optData) {
                                        $pregunta->opciones()->create([
                                            'texto' => $optData['texto'],
                                            'valor' => $optData['valor'],
                                            'orden' => $optData['orden'] ?? $optIdx,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                return ['error' => false, 'message' => 'Evaluación creada', 'data' => $evaluacion->fresh(['servicio', 'niveles', 'perfiles', 'secciones.preguntas.tipo', 'secciones.preguntas.opciones'])];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            return DB::transaction(function () use ($id, $datos) {
                $evaluacion = Evaluacion::find($id);
                if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

                $evaluacion->update([
                    'titulo' => $datos['titulo'] ?? $evaluacion->titulo,
                    'descripcion' => $datos['descripcion'] ?? $evaluacion->descripcion,
                    'id_servicio' => $datos['id_servicio'] ?? $evaluacion->id_servicio,
                    'activo' => $datos['activo'] ?? $evaluacion->activo,
                    'fecha_inicio' => $datos['fecha_inicio'] ?? $evaluacion->fecha_inicio,
                    'fecha_fin' => $datos['fecha_fin'] ?? $evaluacion->fecha_fin,
                ]);

                if (array_key_exists('niveles', $datos)) {
                    $evaluacion->niveles()->sync($datos['niveles'] ?? []);
                }

                if (array_key_exists('perfiles', $datos)) {
                    $evaluacion->perfiles()->sync($datos['perfiles'] ?? []);
                }

                return ['error' => false, 'message' => 'Evaluación actualizada', 'data' => $evaluacion->fresh(['servicio', 'niveles', 'perfiles'])];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $evaluacion = Evaluacion::find($id);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $evaluacion->delete();
            return ['error' => false, 'message' => 'Evaluación eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function toggleActivo(int $id): array
    {
        try {
            $evaluacion = Evaluacion::find($id);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $evaluacion->update(['activo' => $evaluacion->activo ? 0 : 1]);
            return ['error' => false, 'message' => $evaluacion->activo ? 'Evaluación activada' : 'Evaluación desactivada', 'data' => ['activo' => $evaluacion->activo]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Usuarios que pueden ser evaluados en una evaluación, cruzando los
     * perfiles y niveles configurados en ella contra la tabla `usuarios`.
     * Si el solicitante es Coordinador (perfil 26), se restringe además a su
     * propio nivel (un coordinador de primaria no puede evaluar docentes de
     * bachillerato aunque la evaluación cubra varios niveles).
     */
    public function obtenerEvaluables(int $idEvaluacion, Usuario $solicitante): array
    {
        try {
            $evaluacion = Evaluacion::with(['perfiles', 'niveles'])->find($idEvaluacion);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $perfilesEvaluables = $evaluacion->perfiles->pluck('id_perfil');
            if ($perfilesEvaluables->isEmpty()) {
                return ['error' => false, 'message' => 'ok', 'data' => []];
            }

            $query = Usuario::whereIn('perfil', $perfilesEvaluables)
                ->where('estado', 'activo');

            $nivelesEvaluacion = $evaluacion->niveles->pluck('id');
            if ($nivelesEvaluacion->isNotEmpty()) {
                $query->whereIn('id_nivel', $nivelesEvaluacion);
            }

            if ((int) $solicitante->perfil === self::PERFIL_COORDINADOR) {
                $query->where('id_nivel', $solicitante->id_nivel);
            }

            $data = $query->select(['id_user', 'nombre', 'apellido', 'documento', 'perfil', 'id_nivel'])
                ->orderBy('nombre')
                ->get();

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Secciones ──────────────────────────────────────────────

    public function crearSeccion(int $idEvaluacion, array $datos): array
    {
        try {
            $evaluacion = Evaluacion::find($idEvaluacion);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $seccion = $evaluacion->secciones()->create([
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'] ?? null,
                'porcentaje' => $datos['porcentaje'] ?? 0,
                'orden' => $datos['orden'] ?? $evaluacion->secciones()->count(),
                'activo' => $datos['activo'] ?? 1,
            ]);

            return ['error' => false, 'message' => 'Sección creada', 'data' => $seccion];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarSeccion(int $idSeccion, array $datos): array
    {
        try {
            $seccion = EvaluacionSeccion::find($idSeccion);
            if (!$seccion) return ['error' => true, 'message' => 'Sección no encontrada', 'status' => 404];

            $seccion->update($datos);
            return ['error' => false, 'message' => 'Sección actualizada', 'data' => $seccion];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarSeccion(int $idSeccion): array
    {
        try {
            $seccion = EvaluacionSeccion::find($idSeccion);
            if (!$seccion) return ['error' => true, 'message' => 'Sección no encontrada', 'status' => 404];

            $seccion->delete();
            return ['error' => false, 'message' => 'Sección eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Preguntas ─────────────────────────────────────────────

    public function crearPregunta(int $idSeccion, array $datos): array
    {
        try {
            $seccion = EvaluacionSeccion::find($idSeccion);
            if (!$seccion) return ['error' => true, 'message' => 'Sección no encontrada', 'status' => 404];

            $pregunta = $seccion->preguntas()->create([
                'id_tipo_pregunta' => $datos['id_tipo_pregunta'],
                'texto' => $datos['texto'],
                'obligatoria' => $datos['obligatoria'] ?? 1,
                'permite_comentario' => $datos['permite_comentario'] ?? 0,
                'orden' => $datos['orden'] ?? $seccion->preguntas()->count(),
            ]);

            if (!empty($datos['opciones'])) {
                foreach ($datos['opciones'] as $idx => $optData) {
                    $pregunta->opciones()->create([
                        'texto' => $optData['texto'],
                        'valor' => $optData['valor'],
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
            $pregunta = EvaluacionPregunta::find($idPregunta);
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

            $pregunta->update([
                'id_tipo_pregunta' => $datos['id_tipo_pregunta'] ?? $pregunta->id_tipo_pregunta,
                'texto' => $datos['texto'] ?? $pregunta->texto,
                'obligatoria' => $datos['obligatoria'] ?? $pregunta->obligatoria,
                'permite_comentario' => $datos['permite_comentario'] ?? $pregunta->permite_comentario,
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
            $pregunta = EvaluacionPregunta::find($idPregunta);
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

            $pregunta->delete();
            return ['error' => false, 'message' => 'Pregunta eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Opciones de pregunta ───────────────────────────────────

    public function crearOpcion(int $idPregunta, array $datos): array
    {
        try {
            $pregunta = EvaluacionPregunta::find($idPregunta);
            if (!$pregunta) return ['error' => true, 'message' => 'Pregunta no encontrada', 'status' => 404];

            $opcion = $pregunta->opciones()->create([
                'texto' => $datos['texto'],
                'valor' => $datos['valor'],
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
            $opcion = EvaluacionOpcionPregunta::find($idOpcion);
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
            $opcion = EvaluacionOpcionPregunta::find($idOpcion);
            if (!$opcion) return ['error' => true, 'message' => 'Opción no encontrada', 'status' => 404];

            $opcion->delete();
            return ['error' => false, 'message' => 'Opción eliminada'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Respuestas ────────────────────────────────────────────

    public function enviarRespuesta(int $idEvaluacion, int $idUser, array $datos): array
    {
        try {
            return DB::transaction(function () use ($idEvaluacion, $idUser, $datos) {
                $evaluacion = Evaluacion::with(['perfiles', 'niveles'])->find($idEvaluacion);
                if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];
                if (!$evaluacion->activo) return ['error' => true, 'message' => 'La evaluación no está activa', 'status' => 422];

                $perfilesEvaluables = $evaluacion->perfiles->pluck('id_perfil');
                if ($perfilesEvaluables->isNotEmpty()) {
                    $evaluado = Usuario::find($datos['id_evaluado']);
                    if (!$evaluado) return ['error' => true, 'message' => 'Usuario a evaluar no encontrado', 'status' => 404];

                    if (!$perfilesEvaluables->contains((int) $evaluado->perfil)) {
                        return ['error' => true, 'message' => 'El usuario seleccionado no tiene un perfil evaluable en esta evaluación', 'status' => 422];
                    }

                    $nivelesEvaluacion = $evaluacion->niveles->pluck('id');
                    if ($nivelesEvaluacion->isNotEmpty() && !$nivelesEvaluacion->contains((int) $evaluado->id_nivel)) {
                        return ['error' => true, 'message' => 'El usuario seleccionado no pertenece a un nivel evaluable en esta evaluación', 'status' => 422];
                    }
                }

                $anonima = $datos['anonima'] ?? 0;

                $respuestaEval = EvaluacionRespuestaEvaluacion::create([
                    'id_evaluacion' => $idEvaluacion,
                    'id_user' => $anonima ? null : $idUser,
                    'id_evaluado' => $datos['id_evaluado'] ?? null,
                    'id_nivel' => $datos['id_nivel'] ?? null,
                    'anonima' => $anonima,
                    'completada_en' => now(),
                ]);

                if (!empty($datos['respuestas'])) {
                    foreach ($datos['respuestas'] as $respData) {
                        $respuestaEval->respuestasPreguntas()->create([
                            'id_pregunta' => $respData['id_pregunta'],
                            'id_opcion' => $respData['id_opcion'] ?? null,
                            'valor_texto' => $respData['valor_texto'] ?? null,
                            'comentario' => $respData['comentario'] ?? null,
                        ]);
                    }
                }

                return ['error' => false, 'message' => 'Respuesta registrada', 'data' => $respuestaEval->fresh(['respuestasPreguntas.opcion'])];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarRespuestas(int $idEvaluacion, array $filtros): array
    {
        try {
            $evaluacion = Evaluacion::find($idEvaluacion);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $query = EvaluacionRespuestaEvaluacion::with(['usuario', 'evaluado', 'nivel', 'respuestasPreguntas.opcion'])
                ->where('id_evaluacion', $idEvaluacion);

            if (isset($filtros['anonima'])) {
                $query->where('anonima', $filtros['anonima']);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $filtros['per-page'] ?? 15;
            $data = $query->paginate($perPage);

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function obtenerRespuesta(int $idRespuesta): array
    {
        try {
            $respuesta = EvaluacionRespuestaEvaluacion::with([
                'evaluacion.servicio',
                'usuario',
                'evaluado',
                'nivel',
                'respuestasPreguntas.pregunta.tipo',
                'respuestasPreguntas.opcion',
            ])->find($idRespuesta);

            if (!$respuesta) return ['error' => true, 'message' => 'Respuesta no encontrada', 'status' => 404];

            return ['error' => false, 'message' => 'ok', 'data' => $respuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Resultados / Puntaje ──────────────────────────────────

    public function calcularResultados(int $idEvaluacion): array
    {
        try {
            $evaluacion = Evaluacion::with(['secciones.preguntas.opciones'])->find($idEvaluacion);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $respuestas = EvaluacionRespuestaEvaluacion::with(['respuestasPreguntas.opcion'])
                ->where('id_evaluacion', $idEvaluacion)
                ->get();

            if ($respuestas->isEmpty()) {
                return ['error' => false, 'message' => 'Sin respuestas registradas', 'data' => [
                    'total_respuestas' => 0,
                    'promedio_general' => 0,
                    'por_seccion' => [],
                ]];
            }

            $totalRespuestas = $respuestas->count();
            $sumaGeneral = 0;

            $porSeccion = $evaluacion->secciones->map(function ($seccion) use ($respuestas, &$sumaGeneral) {
                $preguntasIds = $seccion->preguntas->pluck('id')->toArray();
                $totalPuntosSeccion = 0;
                $respuestasEnSeccion = 0;

                foreach ($respuestas as $respuesta) {
                    foreach ($respuesta->respuestasPreguntas as $rp) {
                        if (in_array($rp->id_pregunta, $preguntasIds) && $rp->opcion) {
                            $totalPuntosSeccion += $rp->opcion->valor;
                            $respuestasEnSeccion++;
                        }
                    }
                }

                $maxPosible = 0;
                foreach ($seccion->preguntas as $pregunta) {
                    if ($pregunta->opciones->isNotEmpty()) {
                        $maxPosible += $pregunta->opciones->max('valor');
                    }
                }

                $promedioSeccion = $maxPosible > 0 ? round(($totalPuntosSeccion / $maxPosible) * 100, 2) : 0;
                $sumaGeneral += $promedioSeccion * ($seccion->porcentaje / 100);

                return [
                    'id_seccion' => $seccion->id,
                    'titulo' => $seccion->titulo,
                    'porcentaje_ponderacion' => $seccion->porcentaje,
                    'total_respuestas' => $respuestasEnSeccion,
                    'puntaje_obtenido' => round($totalPuntosSeccion, 2),
                    'puntaje_maximo' => round($maxPosible, 2),
                    'promedio' => $promedioSeccion,
                ];
            });

            return ['error' => false, 'message' => 'ok', 'data' => [
                'id_evaluacion' => $evaluacion->id,
                'titulo' => $evaluacion->titulo,
                'total_respuestas' => $totalRespuestas,
                'promedio_general' => round($sumaGeneral, 2),
                'por_seccion' => $porSeccion,
            ]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

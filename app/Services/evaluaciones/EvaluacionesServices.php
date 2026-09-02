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
use App\Models\AnioEscolar\Periodo;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\AnioEscolar\PeriodoServices;
use App\Services\MailService;
use App\Mail\EvaluacionRespuestaMail;
use App\Pdf\Evaluaciones\EvaluacionRespuestaPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EvaluacionesServices
{
    public function __construct(
        private PeriodoServices $periodoServices,
        private AnioEscolarServices $anioEscolarServices,
    ) {}

    // Coordinadores (perfil 26) solo pueden evaluar dentro de su propio nivel
    // (usuarios.id_nivel); el resto de perfiles con acceso administrativo al
    // módulo (Super Admin, Gestión Humana, Administrador, etc.) no tiene esa
    // restricción — ver AGENTS.md, sección "Evaluaciones".
    private const PERFIL_COORDINADOR = 26;

    // Ve todas las evaluaciones sin restricción de nivel/creador — mismo id que
    // App\Http\Middleware\SwitchActiveConnection::PERFIL_SUPER_ADMIN.
    private const PERFIL_SUPER_ADMIN = 1;

    // Administrador general: mismo trato que Super Admin en el listado de
    // configuración de evaluaciones (ve todos los niveles, sin restricción).
    private const PERFIL_ADMIN = 2;

    // Perfiles cuyos usuarios no tienen un nivel institucional real (usuarios.id_nivel
    // queda en NULL/0 para todos ellos) — Proveedor (17) son empresas externas, no
    // personal asignado a un nivel académico/administrativo. Si una evaluación con uno
    // de estos perfiles además tiene niveles configurados (ej. el usuario seleccionó un
    // nivel sin darse cuenta de que no aplica), el filtro de nivel se ignora para ellos
    // en vez de dejar la lista de evaluables vacía.
    private const PERFILES_SIN_NIVEL = [17];

    /** Acceso administrativo pleno al módulo: ve/edita cualquier respuesta sin restricción de nivel/propiedad. */
    private function esAdminEvaluaciones(Usuario $u): bool
    {
        return in_array((int) $u->perfil, [self::PERFIL_SUPER_ADMIN, self::PERFIL_ADMIN], true);
    }

    /**
     * Quién puede VER una respuesta ya guardada (detalle, PDF, reenviar correo): admin pleno,
     * el coordinador de su mismo nivel (`id_nivel` de la respuesta, no necesariamente quien la
     * creó), o el propio usuario evaluado consultando su resultado.
     */
    private function puedeVerRespuesta(Usuario $solicitante, EvaluacionRespuestaEvaluacion $respuesta): bool
    {
        if ($this->esAdminEvaluaciones($solicitante)) return true;

        if ((int) $solicitante->perfil === self::PERFIL_COORDINADOR) {
            return (int) $respuesta->id_nivel === (int) $solicitante->id_nivel;
        }

        return (int) $respuesta->id_evaluado === (int) $solicitante->id_user;
    }

    /** Quién puede EDITAR una respuesta ya guardada: admin pleno, o quien la creó originalmente. */
    private function puedeEditarRespuesta(Usuario $solicitante, EvaluacionRespuestaEvaluacion $respuesta): bool
    {
        return $this->esAdminEvaluaciones($solicitante)
            || (int) $respuesta->id_user === (int) $solicitante->id_user;
    }

    /** Adjunta `foto_url` (pública, del disco de uploads) a partir de la relación `fotoPerfil` ya cargada. */
    private function adjuntarFotoUrl(?Usuario $usuario): void
    {
        if (!$usuario || !$usuario->relationLoaded('fotoPerfil')) return;

        $foto = $usuario->fotoPerfil->first();
        $usuario->setAttribute(
            'foto_url',
            $foto ? Storage::disk(config('filesystems.uploads_disk', 'public'))->url($foto->nombre_foto) : null
        );
    }

    /** Info básica + foto de un usuario evaluable/evaluado, para el encabezado de las pantallas de Realizar/Resultados. */
    public function obtenerUsuarioEvaluado(int $idUsuario): array
    {
        try {
            $usuario = Usuario::with([
                'perfilRelacion:id_perfil,nombre',
                'nivelRelacion:id,nombre',
                'fotoPerfil' => fn ($q) => $q->activas()->latest('id'),
            ])
                ->select(['id_user', 'nombre', 'apellido', 'documento', 'correo', 'perfil', 'id_nivel'])
                ->find($idUsuario);

            if (!$usuario) return ['error' => true, 'message' => 'Usuario no encontrado', 'status' => 404];

            $this->adjuntarFotoUrl($usuario);

            return ['error' => false, 'message' => 'ok', 'data' => $usuario];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Query base de usuarios evaluables de una evaluación: perfil + nivel (con la excepción de PERFILES_SIN_NIVEL) + activos. */
    private function usuariosEvaluablesQuery($perfilesEvaluables, $nivelesEvaluacion)
    {
        $query = Usuario::whereIn('perfil', $perfilesEvaluables)->where('estado', 'activo');

        if ($nivelesEvaluacion->isNotEmpty()) {
            $query->where(function ($q) use ($nivelesEvaluacion) {
                $q->whereIn('id_nivel', $nivelesEvaluacion)
                  ->orWhereIn('perfil', self::PERFILES_SIN_NIVEL);
            });
        }

        return $query;
    }

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

    /**
     * Listado de evaluaciones para la pantalla de configuración. Fuera de Super Admin (1) y
     * Administrador (2), cada usuario solo ve evaluaciones de su propio nivel (o sin nivel
     * configurado, que aplican a todos) — mismo criterio de scoping que
     * listarDisponiblesParaCoordinador, pero sin la excepción "las que él mismo creó" porque
     * esta pantalla es de gestión, no de "evaluaciones que puedo responder".
     */
    public function listar(array $filtros, ?Usuario $solicitante = null): array
    {
        try {
            $query = Evaluacion::with(['servicio', 'niveles', 'perfiles'])
                ->withCount('respuestas');

            if ($solicitante && !in_array((int) $solicitante->perfil, [self::PERFIL_SUPER_ADMIN, self::PERFIL_ADMIN], true)) {
                $idNivel = $solicitante->id_nivel;
                $query->where(function ($q) use ($idNivel) {
                    $q->whereDoesntHave('niveles')
                      ->orWhereHas('niveles', fn ($nq) => $nq->where('nivel.id', $idNivel));
                });
            }

            if (!empty($filtros['id_servicio'])) {
                $idServicio = is_array($filtros['id_servicio']) ? $filtros['id_servicio'] : [$filtros['id_servicio']];
                $query->whereIn('id_servicio', $idServicio);
            }
            if (isset($filtros['activo']) && $filtros['activo'] !== '') {
                $activo = is_array($filtros['activo']) ? $filtros['activo'] : [$filtros['activo']];
                $query->whereIn('activo', $activo);
            }
            if (!empty($filtros['s'])) {
                $busqueda = $filtros['s'];
                $query->where(function ($q) use ($busqueda) {
                    $q->where('titulo', 'like', "%{$busqueda}%")
                      ->orWhere('descripcion', 'like', "%{$busqueda}%");
                });
            }
            // Para el paso "elegí una evaluación" de la pantalla de Resultados: solo
            // evaluaciones que ya tienen al menos una respuesta registrada.
            if (!empty($filtros['con_respuestas'])) {
                $query->having('respuestas_count', '>', 0);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $filtros['per-page'] ?? 15;
            $data = $query->paginate($perPage);

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Evaluaciones que un coordinador puede realizar: las que él mismo creó, o las que
     * incluyen su propio nivel entre los niveles configurados. Super Admin (perfil 1) ve
     * todas las activas sin restricción — ver requerimiento original del módulo.
     */
    public function listarDisponiblesParaCoordinador(Usuario $solicitante): array
    {
        try {
            $query = Evaluacion::with(['servicio', 'niveles', 'perfiles'])
                ->where('activo', 1);

            if ((int) $solicitante->perfil !== self::PERFIL_SUPER_ADMIN) {
                $idNivel = $solicitante->id_nivel;
                $query->where(function ($q) use ($solicitante, $idNivel) {
                    $q->where('id_user', $solicitante->id_user)
                      ->orWhereHas('niveles', fn ($nq) => $nq->where('nivel.id', $idNivel));
                });
            }

            $evaluaciones = $query->orderBy('created_at', 'desc')->get();

            $periodo = $this->periodoServices->resolverActivo();

            $data = $evaluaciones->map(function (Evaluacion $evaluacion) use ($periodo) {
                $perfilesEvaluables = $evaluacion->perfiles->pluck('id_perfil');
                $nivelesEvaluacion = $evaluacion->niveles->pluck('id');

                $evaluablesCount = $perfilesEvaluables->isEmpty()
                    ? 0
                    : $this->usuariosEvaluablesQuery($perfilesEvaluables, $nivelesEvaluacion)->count();

                $evaluadosCount = $periodo
                    ? EvaluacionRespuestaEvaluacion::where('id_evaluacion', $evaluacion->id)
                        ->where('id_periodo', $periodo->id)
                        ->count()
                    : 0;

                $evaluacion->setAttribute('evaluables_count', $evaluablesCount);
                $evaluacion->setAttribute('evaluados_count', $evaluadosCount);

                return $evaluacion;
            });

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

            $nivelesEvaluacion = $evaluacion->niveles->pluck('id');
            $query = $this->usuariosEvaluablesQuery($perfilesEvaluables, $nivelesEvaluacion);

            if ((int) $solicitante->perfil === self::PERFIL_COORDINADOR) {
                $query->where(function ($q) use ($solicitante) {
                    $q->where('id_nivel', $solicitante->id_nivel)
                      ->orWhereIn('perfil', self::PERFILES_SIN_NIVEL);
                });
            }

            $data = $query->select(['id_user', 'nombre', 'apellido', 'documento', 'perfil', 'id_nivel'])
                ->orderBy('nombre')
                ->get();

            // Última evaluación registrada a cada usuario para ESTA evaluación, sin importar
            // el periodo. Es la fuente tanto del flag `evaluado`/`id_respuesta` (así "Editar
            // respuesta"/"Reenviar correo"/"Descargar PDF" en el frontend siempre operan
            // sobre la evaluación más reciente, sin importar en qué periodo se hizo — "Evaluar"
            // sigue disponible aparte para registrar una nueva en un periodo distinto) como de
            // la columna informativa "Última evaluación" del listado (ver frontend
            // Detalle.tsx). El año escolar sale de `anioEscolar` (guardado en la respuesta al
            // momento de enviarla, ver enviarRespuesta), NO de `periodo.anioEscolar` — el año
            // de `periodos` es un catálogo legacy aparte que puede no coincidir.
            $ultimasEvaluaciones = EvaluacionRespuestaEvaluacion::where('id_evaluacion', $idEvaluacion)
                ->whereIn('id_evaluado', $data->pluck('id_user'))
                ->with(['periodo', 'anioEscolar'])
                ->orderByDesc('completada_en')
                ->get()
                ->groupBy('id_evaluado');

            $data->each(function (Usuario $usuario) use ($ultimasEvaluaciones) {
                $ultima = $ultimasEvaluaciones->get($usuario->id_user)?->first();
                $usuario->setAttribute('evaluado', (bool) $ultima);
                $usuario->setAttribute('id_respuesta', $ultima?->id);

                $usuario->setAttribute('ultima_evaluacion', $ultima ? [
                    'id' => $ultima->id,
                    'id_periodo' => $ultima->id_periodo,
                    'numero' => $ultima->periodo?->numero,
                    'anio_escolar' => $ultima->anioEscolar,
                    'completada_en' => $ultima->completada_en,
                ] : null);
            });

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

    public function enviarRespuesta(int $idEvaluacion, Usuario $solicitante, array $datos): array
    {
        try {
            // El periodo lo designa el propio evaluador (ver frontend Responder.tsx) — no se
            // toma un valor por defecto, así que se valida que sea uno de los periodos activos.
            $periodo = Periodo::find($datos['id_periodo'] ?? null);
            if (!$periodo || !$periodo->activo) {
                return ['error' => true, 'message' => 'Selecciona un periodo activo válido', 'status' => 422];
            }

            // El año escolar de la respuesta es el vigente AHORA (tabla `anio_escolar`,
            // resuelto igual que el indicador que ve el evaluador en Responder.tsx) — no el
            // que traiga `periodo.id_anio`, que es un catálogo legacy aparte y puede no
            // coincidir (ver AGENTS.md, sección Evaluaciones).
            $anioEscolar = $this->anioEscolarServices->obtenerUltimoAnioEscolar()['data'] ?? null;

            $resultado = DB::transaction(function () use ($idEvaluacion, $solicitante, $datos, $periodo, $anioEscolar) {
                $evaluacion = Evaluacion::with(['perfiles', 'niveles'])->find($idEvaluacion);
                if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];
                if (!$evaluacion->activo) return ['error' => true, 'message' => 'La evaluación no está activa', 'status' => 422];

                $perfilesEvaluables = $evaluacion->perfiles->pluck('id_perfil');
                $evaluado = null;
                if ($perfilesEvaluables->isNotEmpty()) {
                    $evaluado = Usuario::find($datos['id_evaluado']);
                    if (!$evaluado) return ['error' => true, 'message' => 'Usuario a evaluar no encontrado', 'status' => 404];

                    if (!$perfilesEvaluables->contains((int) $evaluado->perfil)) {
                        return ['error' => true, 'message' => 'El usuario seleccionado no tiene un perfil evaluable en esta evaluación', 'status' => 422];
                    }

                    $evaluadoSinNivel = in_array((int) $evaluado->perfil, self::PERFILES_SIN_NIVEL, true);

                    $nivelesEvaluacion = $evaluacion->niveles->pluck('id');
                    if ($nivelesEvaluacion->isNotEmpty() && !$evaluadoSinNivel && !$nivelesEvaluacion->contains((int) $evaluado->id_nivel)) {
                        return ['error' => true, 'message' => 'El usuario seleccionado no pertenece a un nivel evaluable en esta evaluación', 'status' => 422];
                    }

                    if ((int) $solicitante->perfil === self::PERFIL_COORDINADOR && !$evaluadoSinNivel) {
                        if ((int) $evaluado->id_nivel !== (int) $solicitante->id_nivel) {
                            return ['error' => true, 'message' => 'Solo puedes evaluar usuarios de tu propio nivel', 'status' => 422];
                        }
                    }
                }

                $yaEvaluado = EvaluacionRespuestaEvaluacion::where('id_evaluacion', $idEvaluacion)
                    ->where('id_evaluado', $datos['id_evaluado'] ?? null)
                    ->where('id_periodo', $periodo->id)
                    ->exists();
                if ($yaEvaluado) {
                    return ['error' => true, 'message' => 'Ya se registró una evaluación a este usuario en el periodo actual', 'status' => 422];
                }

                $anonima = $datos['anonima'] ?? 0;

                $respuestaEval = EvaluacionRespuestaEvaluacion::create([
                    'id_evaluacion' => $idEvaluacion,
                    'id_user' => $anonima ? null : $solicitante->id_user,
                    'id_evaluado' => $datos['id_evaluado'] ?? null,
                    // Usuarios de perfiles sin nivel real (proveedores) traen id_nivel en
                    // NULL/0 en `usuarios` — 0 no es un nivel válido y rompe la FK de esta
                    // columna, por eso el `?:` (no `??`) descarta también el 0.
                    'id_nivel' => $datos['id_nivel'] ?? ($evaluado?->id_nivel ?: null),
                    'id_periodo' => $periodo->id,
                    'id_anio_escolar' => $anioEscolar?->id,
                    'anonima' => $anonima,
                    'completada_en' => now(),
                ]);

                $this->guardarRespuestasPreguntas($respuestaEval, $datos['respuestas'] ?? []);

                return ['error' => false, 'message' => 'Respuesta registrada', 'data' => $respuestaEval->fresh(['respuestasPreguntas.opcion'])];
            });

            if (!$resultado['error'] && !empty($resultado['data'])) {
                $this->enviarCorreoRespuesta($resultado['data']);
            }

            return $resultado;
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Reemplaza (delete + recreate) las respuestas por pregunta de una respuesta de evaluación. */
    private function guardarRespuestasPreguntas(EvaluacionRespuestaEvaluacion $respuestaEval, array $respuestas): void
    {
        $respuestaEval->respuestasPreguntas()->delete();

        foreach ($respuestas as $respData) {
            $respuestaEval->respuestasPreguntas()->create([
                'id_pregunta' => $respData['id_pregunta'],
                'id_opcion' => $respData['id_opcion'] ?? null,
                'valor_texto' => $respData['valor_texto'] ?? null,
                'comentario' => $respData['comentario'] ?? null,
            ]);
        }
    }

    /**
     * Genera el PDF de la respuesta y envía el correo con el resumen al evaluado. Se
     * llama fuera de la transacción de guardado — un fallo de correo/PDF no debe hacer
     * rollback de la respuesta ya guardada, solo queda en el log.
     */
    private function enviarCorreoRespuesta(EvaluacionRespuestaEvaluacion $respuestaEval): void
    {
        try {
            $respuestaEval->loadMissing([
                'evaluacion.servicio',
                'evaluado',
                'usuario',
                'nivel',
                'periodo.anioEscolar',
                'respuestasPreguntas.pregunta.tipo',
                'respuestasPreguntas.opcion',
            ]);

            $evaluado = $respuestaEval->evaluado;
            if (!$evaluado || empty($evaluado->correo)) {
                return;
            }

            $pdfBinario = app(EvaluacionRespuestaPdfService::class)->generate($respuestaEval);
            $nombrePdf = 'evaluacion-' . $respuestaEval->id . '.pdf';

            app(MailService::class)->send(
                $evaluado->correo,
                new EvaluacionRespuestaMail($respuestaEval, $pdfBinario, $nombrePdf)
            );
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de la evaluación de desempeño', [
                'id_respuesta' => $respuestaEval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Listado de usuarios evaluados de una evaluación, para la pantalla de Resultados. El
     * coordinador solo ve evaluados de su propio nivel (`id_nivel` de la respuesta); admin
     * pleno (Super Admin/Administrador) puede además filtrar libremente por perfil/nivel.
     */
    public function listarRespuestas(int $idEvaluacion, array $filtros, Usuario $solicitante): array
    {
        try {
            $evaluacion = Evaluacion::find($idEvaluacion);
            if (!$evaluacion) return ['error' => true, 'message' => 'Evaluación no encontrada', 'status' => 404];

            $query = EvaluacionRespuestaEvaluacion::with(['usuario', 'evaluado', 'nivel', 'periodo.anioEscolar', 'respuestasPreguntas.opcion'])
                ->where('id_evaluacion', $idEvaluacion);

            if ((int) $solicitante->perfil === self::PERFIL_COORDINADOR) {
                $query->where('id_nivel', $solicitante->id_nivel);
            } elseif (!empty($filtros['id_nivel'])) {
                $query->where('id_nivel', $filtros['id_nivel']);
            }

            if (!empty($filtros['id_periodo'])) {
                $query->where('id_periodo', $filtros['id_periodo']);
            }

            if (!empty($filtros['id_anio_escolar'])) {
                $idAnio = $filtros['id_anio_escolar'];
                $query->whereHas('periodo', fn ($q) => $q->where('id_anio', $idAnio));
            }

            if (!empty($filtros['perfil'])) {
                $perfilBuscado = $filtros['perfil'];
                $query->whereHas('evaluado', fn ($q) => $q->where('perfil', $perfilBuscado));
            }

            if (!empty($filtros['s'])) {
                $busqueda = $filtros['s'];
                $query->whereHas('evaluado', function ($q) use ($busqueda) {
                    $q->where('nombre', 'like', "%{$busqueda}%")
                      ->orWhere('apellido', 'like', "%{$busqueda}%")
                      ->orWhere('documento', 'like', "%{$busqueda}%")
                      ->orWhere('id_user', 'like', "%{$busqueda}%");
                });
            }

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

    /**
     * Evaluaciones recibidas por el propio usuario autenticado (donde es el evaluado), para
     * el autoservicio "Mis resultados" — sin necesidad de opciones 101/102, cualquier usuario
     * puede ver sus propios resultados. Filtrable por periodo/año escolar.
     */
    public function misResultados(Usuario $solicitante, array $filtros): array
    {
        try {
            $query = EvaluacionRespuestaEvaluacion::with(['evaluacion.servicio', 'periodo.anioEscolar'])
                ->where('id_evaluado', $solicitante->id_user);

            if (!empty($filtros['id_periodo'])) {
                $query->where('id_periodo', $filtros['id_periodo']);
            }

            if (!empty($filtros['id_anio_escolar'])) {
                $idAnio = $filtros['id_anio_escolar'];
                $query->whereHas('periodo', fn ($q) => $q->where('id_anio', $idAnio));
            }

            $data = $query->orderBy('created_at', 'desc')->get();

            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function obtenerRespuesta(int $idRespuesta, Usuario $solicitante): array
    {
        try {
            $respuesta = EvaluacionRespuestaEvaluacion::with([
                'evaluacion.servicio',
                'evaluacion.secciones.preguntas.opciones',
                'usuario',
                'evaluado.perfilRelacion:id_perfil,nombre',
                'evaluado.nivelRelacion:id,nombre',
                'evaluado.fotoPerfil' => fn ($q) => $q->activas()->latest('id'),
                'nivel',
                'periodo.anioEscolar',
                'respuestasPreguntas.pregunta.tipo',
                'respuestasPreguntas.opcion',
            ])->find($idRespuesta);

            if (!$respuesta) return ['error' => true, 'message' => 'Respuesta no encontrada', 'status' => 404];

            if (!$this->puedeVerRespuesta($solicitante, $respuesta)) {
                return ['error' => true, 'message' => 'No tienes acceso a esta respuesta', 'status' => 403];
            }

            $this->adjuntarFotoUrl($respuesta->evaluado);
            $respuesta->setAttribute('resultado', $this->calcularPuntajeRespuesta($respuesta));

            return ['error' => false, 'message' => 'ok', 'data' => $respuesta];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Editar las respuestas ya guardadas — solo quien la creó, o Super Admin. */
    public function actualizarRespuesta(int $idRespuesta, Usuario $solicitante, array $datos): array
    {
        try {
            return DB::transaction(function () use ($idRespuesta, $solicitante, $datos) {
                $respuesta = EvaluacionRespuestaEvaluacion::find($idRespuesta);
                if (!$respuesta) return ['error' => true, 'message' => 'Respuesta no encontrada', 'status' => 404];

                if (!$this->puedeEditarRespuesta($solicitante, $respuesta)) {
                    return ['error' => true, 'message' => 'No tienes acceso a esta respuesta', 'status' => 403];
                }

                $this->guardarRespuestasPreguntas($respuesta, $datos['respuestas'] ?? []);

                return ['error' => false, 'message' => 'Respuesta actualizada', 'data' => $respuesta->fresh(['respuestasPreguntas.opcion'])];
            });
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Reenvía el correo con el PDF actual de la respuesta (regenerado desde el estado vigente). */
    public function reenviarCorreo(int $idRespuesta, Usuario $solicitante): array
    {
        try {
            $respuesta = EvaluacionRespuestaEvaluacion::find($idRespuesta);
            if (!$respuesta) return ['error' => true, 'message' => 'Respuesta no encontrada', 'status' => 404];

            if (!$this->puedeVerRespuesta($solicitante, $respuesta)) {
                return ['error' => true, 'message' => 'No tienes acceso a esta respuesta', 'status' => 403];
            }

            $respuesta->loadMissing('evaluado');
            if (!$respuesta->evaluado || empty($respuesta->evaluado->correo)) {
                return ['error' => true, 'message' => 'El usuario evaluado no tiene un correo registrado', 'status' => 422];
            }

            $this->enviarCorreoRespuesta($respuesta);

            return ['error' => false, 'message' => 'Correo reenviado'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Binario del PDF de una respuesta, para descarga directa (no envía correo). */
    public function generarPdf(int $idRespuesta, Usuario $solicitante): array
    {
        try {
            $respuesta = EvaluacionRespuestaEvaluacion::with([
                'evaluacion.servicio',
                'evaluado',
                'usuario',
                'nivel',
                'periodo.anioEscolar',
                'respuestasPreguntas.pregunta.tipo',
                'respuestasPreguntas.opcion',
            ])->find($idRespuesta);

            if (!$respuesta) return ['error' => true, 'message' => 'Respuesta no encontrada', 'status' => 404];

            if (!$this->puedeVerRespuesta($solicitante, $respuesta)) {
                return ['error' => true, 'message' => 'No tienes acceso a esta respuesta', 'status' => 403];
            }

            $pdfBinario = app(EvaluacionRespuestaPdfService::class)->generate($respuesta);

            return ['error' => false, 'message' => 'ok', 'data' => [
                'contenido' => $pdfBinario,
                'nombre' => 'evaluacion-' . $respuesta->id . '.pdf',
            ]];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Resultados / Puntaje ──────────────────────────────────

    /**
     * Puntaje ponderado de UNA respuesta individual (no el agregado de todos los
     * evaluados de `calcularResultados`) — usado para la tarjeta de promedio en la
     * pantalla "Ver evaluación" de un evaluado puntual. Misma fórmula por sección
     * (puntaje obtenido / puntaje máximo posible * 100, ponderado por
     * `seccion.porcentaje`) pero contra las respuestas de una sola fila de
     * `evaluaciones_respuestas_evaluacion`.
     */
    private function calcularPuntajeRespuesta(EvaluacionRespuestaEvaluacion $respuesta): array
    {
        $sumaGeneral = 0;

        $porSeccion = ($respuesta->evaluacion->secciones ?? collect())->map(function ($seccion) use ($respuesta, &$sumaGeneral) {
            $preguntasIds = $seccion->preguntas->pluck('id')->toArray();

            $puntajeObtenido = 0;
            foreach ($respuesta->respuestasPreguntas as $rp) {
                if (in_array($rp->id_pregunta, $preguntasIds) && $rp->opcion) {
                    $puntajeObtenido += $rp->opcion->valor;
                }
            }

            $maxPosible = 0;
            foreach ($seccion->preguntas as $pregunta) {
                if ($pregunta->opciones->isNotEmpty()) {
                    $maxPosible += $pregunta->opciones->max('valor');
                }
            }

            $promedioSeccion = $maxPosible > 0 ? round(($puntajeObtenido / $maxPosible) * 100, 2) : 0;
            $sumaGeneral += $promedioSeccion * ($seccion->porcentaje / 100);

            return [
                'id_seccion' => $seccion->id,
                'titulo' => $seccion->titulo,
                'porcentaje_ponderacion' => $seccion->porcentaje,
                'puntaje_obtenido' => round($puntajeObtenido, 2),
                'puntaje_maximo' => round($maxPosible, 2),
                'promedio' => $promedioSeccion,
            ];
        })->values();

        return [
            'promedio_general' => round($sumaGeneral, 2),
            'por_seccion' => $porSeccion,
        ];
    }

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

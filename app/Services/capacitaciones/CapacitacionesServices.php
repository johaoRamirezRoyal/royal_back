<?php

namespace App\Services\capacitaciones;

use App\Models\Capacitaciones\CapContenido;
use App\Models\Capacitaciones\CapCurso;
use App\Models\Capacitaciones\CapModulo;
use App\Models\Capacitaciones\CapPregunta;
use App\Models\Usuarios\Usuario;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\FileStorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Capacitación Institucional (Gestión Humana). Migrado de `sami_royal/vistas/modulos/capacitaciones`.
 *
 * Regla única de "capacitación completada" (el legado usaba tres distintas): todos los
 * contenidos ACTUALES del curso vistos Y (el curso no tiene preguntas O el usuario tiene al
 * menos un intento aprobado en `capacitacion_quiz_usuario`). Solo cuentan cursos activos.
 */
class CapacitacionesServices
{
    public const NOTA_MINIMA = 60;

    // Super Admin ve todas las capacitaciones sin estar asignado en capacitacion_perfil (igual que el legado).
    private const PERFIL_SUPER_ADMIN = 1;

    public function __construct(
        private FileStorageService $fileStorage,
        private CloudinaryService $cloudinary,
    ) {}

    /**
     * Migración a Cloudinary: las imágenes nuevas se guardan como URL completa de Cloudinary
     * (carpeta `capacitaciones`); las antiguas siguen siendo un nombre de archivo en el disco
     * de uploads del servidor viejo y se resuelven como siempre.
     */
    private function imagenUrl(?string $imagen): ?string
    {
        return $imagen && str_starts_with($imagen, 'http') ? $imagen : $this->fileStorage->url($imagen);
    }

    // ─── Usuario: realizar capacitaciones ─────────────────────────

    public function cursosDisponibles(Usuario $usuario): array
    {
        try {
            $cursos = $this->queryCursosDelPerfil($usuario->perfil)
                ->withCount('preguntas')
                ->orderByDesc('id')
                ->get();

            $avance = $this->avancePorCurso($usuario->id_user, $cursos->pluck('id'));
            $completados = $this->completados($usuario->id_user)->pluck('id_curso');

            $cursos->each(function (CapCurso $curso) use ($avance, $completados) {
                $curso->imagen_url = $this->imagenUrl($curso->imagen);
                $curso->total_contenidos = $avance[$curso->id]['total'] ?? 0;
                $curso->vistos = $avance[$curso->id]['vistos'] ?? 0;
                $curso->completado = $completados->contains($curso->id);
            });

            return ['error' => false, 'message' => 'Capacitaciones obtenidas correctamente', 'data' => $cursos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalleCurso(int $idCurso, Usuario $usuario, bool $esAdmin): array
    {
        try {
            $curso = CapCurso::with('modulos.contenidos')->withCount('preguntas')->find($idCurso);

            if (!$curso || !$this->puedeVerCurso($curso, $usuario, $esAdmin)) {
                return ['error' => true, 'message' => 'Capacitación no encontrada', 'status' => 404];
            }

            $vistos = $this->contenidosVistos($usuario->id_user, $idCurso);
            $total = 0;

            foreach ($curso->modulos as $modulo) {
                foreach ($modulo->contenidos as $contenido) {
                    $contenido->visto = $vistos->contains($contenido->id);
                    $total++;
                }
            }

            $curso->imagen_url = $this->imagenUrl($curso->imagen);
            $curso->total_contenidos = $total;
            $curso->vistos = $vistos->count();
            $curso->aprobado = $this->aprobado($usuario->id_user, $idCurso);
            $curso->completado = $this->estaCompletado($idCurso, $usuario->id_user);

            return ['error' => false, 'message' => 'Capacitación obtenida correctamente', 'data' => $curso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function marcarVisto(int $idContenido, Usuario $usuario, bool $esAdmin): array
    {
        try {
            $contenido = CapContenido::with('modulo')->find($idContenido);
            $curso = $contenido ? CapCurso::find($contenido->modulo?->id_curso) : null;

            if (!$curso || !$this->puedeVerCurso($curso, $usuario, $esAdmin)) {
                return ['error' => true, 'message' => 'Contenido no encontrado', 'status' => 404];
            }

            // Sin restricción única en la tabla: se evita el duplicado aquí.
            DB::table('capacitacion_progreso_usuario')->updateOrInsert(
                ['id_user' => $usuario->id_user, 'id_contenido' => $idContenido],
                ['id_curso' => $curso->id]
            );

            return ['error' => false, 'message' => 'Contenido marcado como visto', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function preguntasQuiz(int $idCurso, Usuario $usuario, bool $esAdmin): array
    {
        try {
            $curso = CapCurso::find($idCurso);

            if (!$curso || !$this->puedeVerCurso($curso, $usuario, $esAdmin)) {
                return ['error' => true, 'message' => 'Capacitación no encontrada', 'status' => 404];
            }

            if (!$this->contenidosCompletos($usuario->id_user, $idCurso)) {
                return ['error' => true, 'message' => 'Debes ver todos los contenidos antes de presentar la prueba', 'status' => 422];
            }

            // `esCorrecto` va oculto en el modelo CapOpcion.
            $preguntas = CapPregunta::with('opciones')->where('id_capacitacion', $idCurso)->orderBy('id')->get();

            return ['error' => false, 'message' => 'Prueba obtenida correctamente', 'data' => $preguntas];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<int|string, int> $respuestas id_pregunta => id_opcion
     */
    public function responderQuiz(int $idCurso, array $respuestas, Usuario $usuario, bool $esAdmin): array
    {
        try {
            $curso = CapCurso::find($idCurso);

            if (!$curso || !$this->puedeVerCurso($curso, $usuario, $esAdmin)) {
                return ['error' => true, 'message' => 'Capacitación no encontrada', 'status' => 404];
            }

            if (!$this->contenidosCompletos($usuario->id_user, $idCurso)) {
                return ['error' => true, 'message' => 'Debes ver todos los contenidos antes de presentar la prueba', 'status' => 422];
            }

            $idsPreguntas = CapPregunta::where('id_capacitacion', $idCurso)->pluck('id');

            if ($idsPreguntas->isEmpty()) {
                return ['error' => true, 'message' => 'Esta capacitación no tiene prueba final', 'status' => 422];
            }

            if ($idsPreguntas->diff(array_map('intval', array_keys($respuestas)))->isNotEmpty()) {
                return ['error' => true, 'message' => 'Debes responder todas las preguntas', 'status' => 422];
            }

            $correctas = 0;
            foreach ($idsPreguntas as $idPregunta) {
                $correctas += DB::table('capacitacion_opciones')
                    ->where('id', (int) $respuestas[$idPregunta])
                    ->where('id_pregunta', $idPregunta)
                    ->where('esCorrecto', 1)
                    ->exists() ? 1 : 0;
            }

            $porcentaje = (int) round($correctas / $idsPreguntas->count() * 100);
            $aprobado = $porcentaje >= self::NOTA_MINIMA;

            // Reintentos ilimitados; la tabla solo guarda aprobado (1) / reprobado (0).
            DB::table('capacitacion_quiz_usuario')->insert([
                'id_curso' => $idCurso,
                'id_usuario' => $usuario->id_user,
                'resultado' => $aprobado ? 1 : 0,
            ]);

            return [
                'error' => false,
                'message' => $aprobado ? '¡Aprobaste la prueba!' : 'No aprobaste la prueba, vuelve a intentarlo',
                'data' => [
                    'porcentaje' => $porcentaje,
                    'correctas' => $correctas,
                    'total' => $idsPreguntas->count(),
                    'aprobado' => $aprobado,
                    'nota_minima' => self::NOTA_MINIMA,
                ],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Capacitaciones completadas por un usuario, con la fecha de finalización. */
    public function completadasDeUsuario(int $idUser): array
    {
        try {
            $completados = $this->completados($idUser)->keyBy('id_curso');

            $cursos = CapCurso::whereIn('id', $completados->keys())
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'imagen'])
                ->each(function (CapCurso $curso) use ($completados) {
                    $curso->imagen_url = $this->imagenUrl($curso->imagen);
                    $curso->fecha_finalizacion = $completados[$curso->id]['fecha'];
                });

            return ['error' => false, 'message' => 'Capacitaciones completadas obtenidas correctamente', 'data' => $cursos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Datos para el PDF, solo si el usuario completó la capacitación. */
    public function datosCertificado(int $idCurso, int $idUser): array
    {
        try {
            $completado = $this->completados($idUser)->firstWhere('id_curso', $idCurso);

            if (!$completado) {
                return ['error' => true, 'message' => 'La capacitación aún no ha sido completada', 'status' => 422];
            }

            return ['error' => false, 'message' => 'ok', 'data' => [
                'curso' => CapCurso::find($idCurso),
                'usuario' => Usuario::find($idUser, ['id_user', 'nombre', 'apellido', 'documento']),
                'fecha' => $completado['fecha'],
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Capacitaciones realizadas (opción 93) ────────────────────

    public function usuariosConCapacitaciones(array $filtros, int $perPage): array
    {
        try {
            $conteo = $this->completados()->countBy('id_user');

            $query = Usuario::query()
                ->whereIn('id_user', $conteo->keys())
                ->with('perfilRelacion:id_perfil,nombre')
                ->select(['id_user', 'nombre', 'apellido', 'documento', 'perfil'])
                ->orderBy('nombre')
                ->orderBy('apellido');

            if (!empty($filtros['s'])) {
                $search = $filtros['s'];
                $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                    ->orWhere('documento', 'like', "%{$search}%"));
            }

            if (!empty($filtros['id_nivel'])) {
                $query->where('id_nivel', (int) $filtros['id_nivel']);
            }

            if (!empty($filtros['perfil'])) {
                $query->where('perfil', (int) $filtros['perfil']);
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(function (Usuario $u) use ($conteo) {
                $u->total_capacitaciones = $conteo[$u->id_user] ?? 0;
                $u->nombre_perfil = $u->perfilRelacion?->nombre;
                $u->unsetRelation('perfilRelacion');
            });

            return ['error' => false, 'message' => 'Usuarios obtenidos correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Administración (opción 86) ───────────────────────────────

    public function listarCursosAdmin(array $filtros, int $perPage): array
    {
        try {
            $query = CapCurso::withCount(['modulos', 'preguntas'])->orderByDesc('id');

            if (!empty($filtros['s'])) {
                $query->where('nombre', 'like', "%{$filtros['s']}%");
            }

            if (isset($filtros['activo']) && $filtros['activo'] !== '') {
                $query->where('activo', (int) $filtros['activo']);
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($c) => $c->imagen_url = $this->imagenUrl($c->imagen));

            return ['error' => false, 'message' => 'Capacitaciones obtenidas correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarCurso(?int $idCurso, array $data, ?UploadedFile $imagen, int $idLog): array
    {
        try {
            $curso = $idCurso ? CapCurso::find($idCurso) : new CapCurso(['id_log' => $idLog, 'activo' => 1]);

            if (!$curso) {
                return ['error' => true, 'message' => 'Capacitación no encontrada', 'status' => 404];
            }

            $curso->fill([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? 'Sin descripción',
            ]);

            DB::transaction(function () use ($curso, $imagen) {
                $curso->save();

                if (!$imagen) {
                    return;
                }

                // public_id fijo por curso (`capacitaciones/curso_{id}`, con overwrite): cambiar la
                // imagen reemplaza el mismo recurso en Cloudinary en vez de dejar huérfanos.
                // ponytail: URL completa (~90 chars) en `imagen` varchar(100) — ampliar la columna si el cloud_name crece.
                $subida = $this->cloudinary->uploadFile($imagen, 'capacitaciones', 'curso_' . $curso->id);

                if ($subida['error']) {
                    throw new Exception($subida['message']);
                }

                $anterior = $curso->imagen;
                $curso->imagen = $subida['data']['url'];
                $curso->save();

                // Imagen antigua del servidor viejo: ya no se usa, se borra del disco.
                if ($anterior && !str_starts_with($anterior, 'http')) {
                    $this->fileStorage->eliminar($anterior);
                }
            });

            return ['error' => false, 'message' => 'Capacitación guardada correctamente', 'data' => $curso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function cambiarEstadoCurso(int $idCurso, bool $activo): array
    {
        try {
            $actualizados = CapCurso::where('id', $idCurso)->update(['activo' => $activo ? 1 : 0]);

            if (!$actualizados) {
                return ['error' => true, 'message' => 'Capacitación no encontrada', 'status' => 404];
            }

            return ['error' => false, 'message' => $activo ? 'Capacitación activada' : 'Capacitación desactivada', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function modulosAdmin(int $idCurso): array
    {
        try {
            $modulos = CapModulo::with('contenidos')->where('id_curso', $idCurso)->orderBy('id')->get();

            return ['error' => false, 'message' => 'Módulos obtenidos correctamente', 'data' => $modulos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarModulo(?int $idModulo, array $data, int $idLog): array
    {
        try {
            $modulo = $idModulo ? CapModulo::find($idModulo) : new CapModulo(['id_curso' => $data['id_curso'], 'id_log' => $idLog]);

            if (!$modulo) {
                return ['error' => true, 'message' => 'Módulo no encontrado', 'status' => 404];
            }

            $modulo->fill([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? 'Sin descripción',
            ])->save();

            return ['error' => false, 'message' => 'Módulo guardado correctamente', 'data' => $modulo];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Borrado físico en cascada: contenidos del módulo y el progreso registrado sobre ellos. */
    public function eliminarModulo(int $idModulo): array
    {
        try {
            DB::transaction(function () use ($idModulo) {
                $idsContenidos = CapContenido::where('id_modulo', $idModulo)->pluck('id');
                DB::table('capacitacion_progreso_usuario')->whereIn('id_contenido', $idsContenidos)->delete();
                CapContenido::whereIn('id', $idsContenidos)->delete();
                CapModulo::where('id', $idModulo)->delete();
            });

            return ['error' => false, 'message' => 'Módulo eliminado correctamente', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarContenido(?int $idContenido, array $data, int $idLog): array
    {
        try {
            $contenido = $idContenido
                ? CapContenido::find($idContenido)
                : new CapContenido(['id_modulo' => $data['id_modulo'], 'id_log' => $idLog]);

            if (!$contenido) {
                return ['error' => true, 'message' => 'Contenido no encontrado', 'status' => 404];
            }

            $contenido->fill([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'contenido' => $data['contenido'],
            ])->save();

            return ['error' => false, 'message' => 'Contenido guardado correctamente', 'data' => $contenido];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarContenido(int $idContenido): array
    {
        try {
            DB::transaction(function () use ($idContenido) {
                DB::table('capacitacion_progreso_usuario')->where('id_contenido', $idContenido)->delete();
                CapContenido::where('id', $idContenido)->delete();
            });

            return ['error' => false, 'message' => 'Contenido eliminado correctamente', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function quizAdmin(int $idCurso): array
    {
        try {
            $preguntas = CapPregunta::with('opciones')->where('id_capacitacion', $idCurso)->orderBy('id')->get();
            $preguntas->each(fn ($p) => $p->opciones->each->makeVisible('esCorrecto'));

            return ['error' => false, 'message' => 'Prueba obtenida correctamente', 'data' => $preguntas];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reemplaza la prueba completa del curso. Un arreglo vacío elimina la prueba.
     *
     * @param array<int, array{enunciado: string, opciones: array<int, array{enunciado: string, esCorrecto: bool}>}> $preguntas
     */
    public function guardarQuiz(int $idCurso, array $preguntas, int $idLog): array
    {
        try {
            DB::transaction(function () use ($idCurso, $preguntas, $idLog) {
                $idsAnteriores = CapPregunta::where('id_capacitacion', $idCurso)->pluck('id');
                DB::table('capacitacion_opciones')->whereIn('id_pregunta', $idsAnteriores)->delete();
                CapPregunta::whereIn('id', $idsAnteriores)->delete();

                foreach ($preguntas as $p) {
                    $pregunta = CapPregunta::create([
                        'enunciado' => $p['enunciado'],
                        'id_capacitacion' => $idCurso,
                        'id_log' => $idLog,
                    ]);

                    $pregunta->opciones()->createMany(array_map(fn ($o) => [
                        'enunciado' => $o['enunciado'],
                        'esCorrecto' => (bool) $o['esCorrecto'],
                        'id_log' => $idLog,
                    ], $p['opciones']));
                }
            });

            return ['error' => false, 'message' => 'Prueba guardada correctamente', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function perfilesCurso(int $idCurso): array
    {
        try {
            $perfiles = DB::table('capacitacion_perfil')->where('id_curso', $idCurso)->pluck('id_perfil')->map(fn ($p) => (int) $p);

            return ['error' => false, 'message' => 'Perfiles obtenidos correctamente', 'data' => $perfiles->values()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function sincronizarPerfiles(int $idCurso, array $perfiles, int $idLog): array
    {
        try {
            DB::transaction(function () use ($idCurso, $perfiles, $idLog) {
                DB::table('capacitacion_perfil')->where('id_curso', $idCurso)->delete();
                DB::table('capacitacion_perfil')->insert(array_map(fn ($idPerfil) => [
                    'id_curso' => $idCurso,
                    'id_perfil' => (int) $idPerfil,
                    'id_log' => $idLog,
                ], array_unique($perfiles)));
            });

            return ['error' => false, 'message' => 'Perfiles actualizados correctamente', 'data' => null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Reglas internas ──────────────────────────────────────────

    private function queryCursosDelPerfil(int $perfil)
    {
        $query = CapCurso::where('activo', 1);

        if ($perfil !== self::PERFIL_SUPER_ADMIN) {
            $query->whereIn('id', DB::table('capacitacion_perfil')->where('id_perfil', $perfil)->select('id_curso'));
        }

        return $query;
    }

    // Los administradores (86) pueden previsualizar cualquier curso, incluso desactivado.
    private function puedeVerCurso(CapCurso $curso, Usuario $usuario, bool $esAdmin): bool
    {
        return $esAdmin || $this->queryCursosDelPerfil($usuario->perfil)->where('id', $curso->id)->exists();
    }

    private function contenidosVistos(int $idUser, int $idCurso): Collection
    {
        return DB::table('capacitacion_progreso_usuario as p')
            ->join('capacitaciones_contenido as c', 'c.id', '=', 'p.id_contenido')
            ->join('capacitacion_modulos as m', 'm.id', '=', 'c.id_modulo')
            ->where('m.id_curso', $idCurso)
            ->where('p.id_user', $idUser)
            ->distinct()
            ->pluck('p.id_contenido')
            ->map(fn ($id) => (int) $id);
    }

    private function contenidosCompletos(int $idUser, int $idCurso): bool
    {
        $avance = $this->avancePorCurso($idUser, collect([$idCurso]))[$idCurso] ?? null;

        return $avance && $avance['total'] > 0 && $avance['vistos'] >= $avance['total'];
    }

    private function aprobado(int $idUser, int $idCurso): bool
    {
        return DB::table('capacitacion_quiz_usuario')
            ->where('id_usuario', $idUser)->where('id_curso', $idCurso)->where('resultado', 1)
            ->exists();
    }

    private function estaCompletado(int $idCurso, int $idUser): bool
    {
        return $this->completados($idUser)->contains('id_curso', $idCurso);
    }

    /** @return array<int, array{total: int, vistos: int}> indexado por id_curso */
    private function avancePorCurso(?int $idUser, Collection $idsCursos): array
    {
        $totales = DB::table('capacitaciones_contenido as c')
            ->join('capacitacion_modulos as m', 'm.id', '=', 'c.id_modulo')
            ->whereIn('m.id_curso', $idsCursos)
            ->groupBy('m.id_curso')
            ->selectRaw('m.id_curso, COUNT(c.id) as total')
            ->pluck('total', 'id_curso');

        $vistos = DB::table('capacitacion_progreso_usuario as p')
            ->join('capacitaciones_contenido as c', 'c.id', '=', 'p.id_contenido')
            ->join('capacitacion_modulos as m', 'm.id', '=', 'c.id_modulo')
            ->whereIn('m.id_curso', $idsCursos)
            ->where('p.id_user', $idUser)
            ->groupBy('m.id_curso')
            ->selectRaw('m.id_curso, COUNT(DISTINCT p.id_contenido) as vistos')
            ->pluck('vistos', 'id_curso');

        return $idsCursos->mapWithKeys(fn ($id) => [$id => [
            'total' => (int) ($totales[$id] ?? 0),
            'vistos' => (int) ($vistos[$id] ?? 0),
        ]])->all();
    }

    /**
     * Pares (usuario, curso) completados según la regla única, con la fecha de finalización
     * (último contenido visto o último intento aprobado, la más reciente).
     * ponytail: calcula en PHP sobre todos los usuarios cuando $idUser es null; pasar a una
     * vista SQL si capacitacion_progreso_usuario crece a cientos de miles de filas.
     *
     * @return Collection<int, array{id_user: int, id_curso: int, fecha: string}>
     */
    private function completados(?int $idUser = null): Collection
    {
        $totales = DB::table('capacitaciones_contenido as c')
            ->join('capacitacion_modulos as m', 'm.id', '=', 'c.id_modulo')
            ->join('capacitacion_curso as cc', 'cc.id', '=', 'm.id_curso')
            ->where('cc.activo', 1)
            ->groupBy('m.id_curso')
            ->selectRaw('m.id_curso, COUNT(c.id) as total')
            ->pluck('total', 'id_curso');

        $conQuiz = DB::table('capacitacion_preguntas')->distinct()->pluck('id_capacitacion')->flip();

        $aprobados = DB::table('capacitacion_quiz_usuario')
            ->where('resultado', 1)
            ->when($idUser, fn ($q) => $q->where('id_usuario', $idUser))
            ->groupBy('id_usuario', 'id_curso')
            ->get(['id_usuario', 'id_curso', DB::raw('MAX(fechareg) as fecha')])
            ->keyBy(fn ($r) => $r->id_usuario . '-' . $r->id_curso);

        return DB::table('capacitacion_progreso_usuario as p')
            ->join('capacitaciones_contenido as c', 'c.id', '=', 'p.id_contenido')
            ->join('capacitacion_modulos as m', 'm.id', '=', 'c.id_modulo')
            ->whereIn('m.id_curso', $totales->keys())
            ->when($idUser, fn ($q) => $q->where('p.id_user', $idUser))
            ->groupBy('p.id_user', 'm.id_curso')
            ->get(['p.id_user', 'm.id_curso', DB::raw('COUNT(DISTINCT p.id_contenido) as vistos'), DB::raw('MAX(p.fechareg) as fecha')])
            ->filter(function ($r) use ($totales, $conQuiz, $aprobados) {
                if ((int) $r->vistos < (int) $totales[$r->id_curso]) {
                    return false;
                }

                return !isset($conQuiz[$r->id_curso]) || isset($aprobados[$r->id_user . '-' . $r->id_curso]);
            })
            ->map(fn ($r) => [
                'id_user' => (int) $r->id_user,
                'id_curso' => (int) $r->id_curso,
                'fecha' => max((string) $r->fecha, (string) ($aprobados[$r->id_user . '-' . $r->id_curso]->fecha ?? '')),
            ])
            ->values();
    }
}

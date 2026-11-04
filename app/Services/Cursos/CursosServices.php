<?php

namespace App\Services\Cursos;

use App\Models\Areas\Cursos;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Support\Permiso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CursosServices
{
    public const PERFIL_ESTUDIANTE = 16;

    public function __construct(
        private AnioEscolarServices $anios,
    ) {}

    /**
     * Avisos de la página de inicio al terminar el año escolar: si aún no se promovió a los
     * estudiantes del año que cerró y si el año siguiente no tiene periodos académicos.
     * null fuera de la ventana de cierre (ver AnioEscolarServices::estadoCierreAnio).
     */
    public function avisosCierreAnio(): array
    {
        try {
            $estado = $this->anios->estadoCierreAnio();

            if (! $estado) {
                return ['error' => false, 'message' => 'Sin avisos de cierre de año', 'data' => null];
            }

            $nombre = fn ($a) => $a ? ($a->anio_inicio == $a->anio_fin ? (string) $a->anio_inicio : "{$a->anio_inicio}-{$a->anio_fin}") : null;
            $cerrado = $estado['anio_cerrado'];
            $siguiente = $estado['anio_siguiente'];

            return ['error' => false, 'message' => 'Avisos de cierre de año', 'data' => [
                'anio_cerrado' => ['id' => $cerrado->id, 'nombre' => $nombre($cerrado)],
                'anio_siguiente' => $siguiente ? ['id' => $siguiente->id, 'nombre' => $nombre($siguiente)] : null,
                'promovido' => DB::table('cursos_promociones')->where('id_anio_escolar', $cerrado->id)->exists(),
                'periodos_definidos' => $estado['periodos_siguiente'] > 0,
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getCursos()
    {
        try {
            // Con su nivel y en orden académico: los selects del frontend los agrupan por nivel.
            return Cursos::ordenarPorGrado(
                Cursos::with('nivel:id,nombre')->get(['id', 'nombre', 'curso_proximo', 'activo', 'id_nivel'])
            );
        } catch (Throwable $err) {
            return [
                'error' => true,
                'message' => $err->getMessage(),
            ];
        }
    }

    /** Docentes activos para elegir director de grupo, con el curso que ya dirigen (si alguno). */
    public function docentes(): array
    {
        try {
            $dirigidos = Cursos::whereNotNull('id_director')->get(['id', 'nombre', 'id_director'])->keyBy('id_director');

            $docentes = Usuario::whereIn('perfil', Permiso::PERFILES_DOCENTE)
                ->where('estado', 'activo')
                ->orderByRaw('TRIM(nombre)') // legacy: hay nombres con espacios al inicio
                ->get(['id_user', 'nombre', 'apellido'])
                ->map(fn ($d) => [
                    'id_user' => $d->id_user,
                    'nombre' => $d->nombre,
                    'apellido' => $d->apellido,
                    'curso_dirigido' => ($c = $dirigidos->get($d->id_user)) ? ['id' => $c->id, 'nombre' => $c->nombre] : null,
                ]);

            return ['error' => false, 'message' => 'Docentes obtenidos correctamente', 'data' => $docentes];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Cursos con nivel, curso próximo y cuántos estudiantes activos tienen hoy. */
    private function query()
    {
        return Cursos::with(['nivel:id,nombre', 'proximo:id,nombre,activo', 'director:id_user,nombre,apellido'])
            ->withCount(['usuarios as total_estudiantes' => fn ($q) => $q
                ->where('perfil', self::PERFIL_ESTUDIANTE)
                ->where('estado', 'activo')]);
    }

    public function listar(?string $s, ?string $estado, int $perPage): array
    {
        try {
            $paginator = $this->query()
                ->when($s, fn ($q) => $q->where('nombre', 'like', "%{$s}%"))
                ->when($estado === 'activo', fn ($q) => $q->where('activo', 1))
                ->when($estado === 'inactivo', fn ($q) => $q->where('activo', 0))
                ->orderByRaw('id_nivel IS NULL, id_nivel') // sin nivel (p. ej. "Egresado") al final
                ->orderBy('nombre')
                ->paginate($perPage);

            return ['error' => false, 'message' => 'Cursos obtenidos correctamente', 'data' => $paginator];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalle(int $id): array
    {
        $curso = $this->query()->find($id);

        return $curso
            ? ['error' => false, 'message' => 'Curso obtenido correctamente', 'data' => $curso]
            : ['error' => true, 'message' => 'El curso no existe', 'status' => 404];
    }

    public function crear(array $data, int $idLog): array
    {
        try {
            $deCurso = $this->reasignarDirector($data['id_director'] ?? null, null);

            $curso = Cursos::create([
                'nombre' => trim($data['nombre']),
                'id_nivel' => $data['id_nivel'],
                'curso_proximo' => $data['curso_proximo'] ?? null,
                'id_director' => $data['id_director'] ?? null,
                'activo' => (int) ($data['activo'] ?? 1),
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Curso creado correctamente' . $this->avisoReasignado($deCurso), 'data' => $curso];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, int $idLog): array
    {
        try {
            $curso = Cursos::find($id);

            if (! $curso) {
                return ['error' => true, 'message' => 'El curso no existe', 'status' => 404];
            }

            $deCurso = $this->reasignarDirector($data['id_director'] ?? null, $curso->id);

            $curso->update([
                'nombre' => trim($data['nombre']),
                'id_nivel' => $data['id_nivel'],
                'curso_proximo' => $data['curso_proximo'] ?? null,
                'id_director' => $data['id_director'] ?? null,
                'activo' => (int) ($data['activo'] ?? $curso->activo),
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Curso actualizado correctamente' . $this->avisoReasignado($deCurso), 'data' => $curso];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Un docente dirige un solo curso: si ya dirigía otro (distinto de $idCurso), ese queda sin
     * director. Devuelve el nombre del curso que dejó, para avisarlo.
     */
    private function reasignarDirector(?int $idDirector, ?int $idCurso): ?string
    {
        if (! $idDirector) {
            return null;
        }

        $otros = Cursos::where('id_director', $idDirector)->when($idCurso, fn ($q) => $q->where('id', '!=', $idCurso));
        $nombre = (clone $otros)->value('nombre');
        $otros->update(['id_director' => null]);

        return $nombre;
    }

    private function avisoReasignado(?string $deCurso): string
    {
        return $deCurso ? ". {$deCurso} quedó sin director de grupo" : '';
    }

    /**
     * Pone los estudiantes en el curso. Si ya estaban en otro, se mueven: un estudiante tiene
     * un solo curso (usuarios.id_curso). Solo toca usuarios con perfil Estudiante.
     */
    public function asignarEstudiantes(int $idCurso, array $idsEstudiantes): array
    {
        try {
            $curso = Cursos::find($idCurso);

            if (! $curso) {
                return ['error' => true, 'message' => 'El curso no existe', 'status' => 404];
            }

            if (! $curso->activo) {
                return ['error' => true, 'message' => 'No se pueden asignar estudiantes a un curso inactivo'];
            }

            $total = Usuario::whereIn('id_user', $idsEstudiantes)
                ->where('perfil', self::PERFIL_ESTUDIANTE)
                ->update(['id_curso' => $idCurso]);

            return ['error' => false, 'message' => "Se asignaron {$total} estudiante(s) a {$curso->nombre}", 'data' => ['total' => $total]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Vista previa de la promoción: cada curso activo con estudiantes, a dónde pasan y si se
     * puede (sin curso próximo, o con uno inactivo o inexistente, se queda donde está).
     */
    public function previaPromocion(): array
    {
        try {
            // Sin nivel = no es un grado que se promueva ("Egresado", "Estudiantes Graduados"…).
            $cursos = Cursos::ordenarPorGrado($this->query()->where('activo', 1)->whereNotNull('id_nivel')->get())
                ->filter(fn ($c) => $c->total_estudiantes > 0)
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'total_estudiantes' => $c->total_estudiantes,
                    'proximo' => $c->proximo ? ['id' => $c->proximo->id, 'nombre' => $c->proximo->nombre, 'activo' => (bool) $c->proximo->activo] : null,
                    'promovible' => (bool) $c->proximo?->activo,
                    'director' => $c->director ? ['id_user' => $c->director->id_user, 'nombre' => $c->director->nombre, 'apellido' => $c->director->apellido] : null,
                ])
                ->values();

            return ['error' => false, 'message' => 'Vista previa de la promoción', 'data' => $cursos];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Estudiantes activos de esos cursos, para revisar uno a uno antes de promover.
     * `sugerido_repetir`: su director de grupo sugirió que repita el año que se cierra.
     */
    public function estudiantesParaPromocion(array $idsCursos): array
    {
        try {
            $estudiantes = $this->conSugerencia(Usuario::whereIn('id_curso', $idsCursos))
                ->orderBy('nombre') // legacy: muchos traen el nombre completo aquí y "." de apellido
                ->get();

            return ['error' => false, 'message' => 'Estudiantes a promover', 'data' => $estudiantes];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Estudiantes activos de la consulta con su marca `sugerido_repetir` del año que se cierra. */
    private function conSugerencia($query)
    {
        $anio = $this->anioQueSeCierra();

        return $query->where('perfil', self::PERFIL_ESTUDIANTE)
            ->where('estado', 'activo')
            ->select(['id_user', 'nombre', 'apellido', 'documento', 'id_curso'])
            ->selectRaw(
                'EXISTS (SELECT 1 FROM promocion_sugerencias s WHERE s.id_estudiante = usuarios.id_user AND s.id_anio_escolar = ?) as sugerido_repetir',
                [$anio ?? 0],
            );
    }

    /**
     * "Mi curso" del director de grupo: su curso activo, a dónde pasan y sus estudiantes con la
     * marca de quién sugirió que repita. null si no dirige ninguno.
     */
    public function miCurso(int $idDocente): array
    {
        try {
            $curso = Cursos::with('proximo:id,nombre')->where('id_director', $idDocente)->where('activo', 1)->first(['id', 'nombre', 'curso_proximo']);

            if (! $curso) {
                return ['error' => false, 'message' => 'No diriges ningún curso', 'data' => null];
            }

            return ['error' => false, 'message' => 'Tu curso', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre, 'proximo' => $curso->proximo?->nombre],
                'estudiantes' => $this->conSugerencia(Usuario::where('id_curso', $curso->id))
                    ->addSelect('foto_carnet') // la lista muestra la foto de cada estudiante
                    // Estado del último carnet (null = nunca se le imprimió), para "Carnets por entregar".
                    ->selectRaw('(SELECT k.estado FROM carnets k WHERE k.id_user = usuarios.id_user ORDER BY k.id DESC LIMIT 1) as carnet_estado')
                    // Tipo de la solicitud de carnet pendiente (null si no hay), para no pedirla dos veces.
                    ->selectRaw("(SELECT cs.tipo FROM carnet_solicitudes cs WHERE cs.id_user = usuarios.id_user AND cs.estado = 'PENDIENTE' LIMIT 1) as solicitud_carnet")
                    ->orderBy('nombre')
                    ->get(),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Estudiantes activos que hoy están en el curso que dirige el docente: de esos ids, o todos
     * con null.
     */
    public function estudiantesDeMiCurso(int $idDocente, ?array $idsEstudiantes): array
    {
        $idCurso = Cursos::where('id_director', $idDocente)->where('activo', 1)->value('id');

        return $idCurso
            ? Usuario::where('id_curso', $idCurso)->where('perfil', self::PERFIL_ESTUDIANTE)->where('estado', 'activo')
                ->when($idsEstudiantes !== null, fn ($q) => $q->whereIn('id_user', $idsEstudiantes))->pluck('id_user')->all()
            : [];
    }

    /**
     * Guarda quiénes sugiere el director que repitan (reemplaza su lista del año que se cierra).
     * Solo estudiantes que hoy están en su curso; los demás ids se ignoran.
     */
    public function sugerirRepiten(int $idDocente, array $idsEstudiantes): array
    {
        try {
            $curso = Cursos::where('id_director', $idDocente)->where('activo', 1)->first(['id', 'nombre']);
            $anio = $this->anioQueSeCierra();

            if (! $curso) {
                return ['error' => true, 'message' => 'No diriges ningún curso', 'status' => 403];
            }
            if (! $anio) {
                return ['error' => true, 'message' => 'No hay un año escolar vigente'];
            }

            $validos = Usuario::where('id_curso', $curso->id)->where('perfil', self::PERFIL_ESTUDIANTE)->where('estado', 'activo')
                ->whereIn('id_user', $idsEstudiantes)->pluck('id_user');

            DB::transaction(function () use ($curso, $anio, $validos, $idDocente) {
                // Un estudiante solo puede tener una sugerencia por año (único): se borra también la
                // que tuviera desde otro curso antes de pasarse a este.
                DB::table('promocion_sugerencias')->where('id_anio_escolar', $anio)
                    ->where(fn ($q) => $q->where('id_curso', $curso->id)->orWhereIn('id_estudiante', $validos))
                    ->delete();
                DB::table('promocion_sugerencias')->insert($validos->map(fn ($id) => [
                    'id_anio_escolar' => $anio,
                    'id_curso' => $curso->id,
                    'id_estudiante' => $id,
                    'id_director' => $idDocente,
                    'fecha' => now(),
                ])->all());
            });

            return ['error' => false, 'message' => $validos->isEmpty()
                ? "Ningún estudiante de {$curso->nombre} quedó sugerido para repetir"
                : "Se sugirió que {$validos->count()} estudiante(s) de {$curso->nombre} repitan", 'data' => ['total' => $validos->count()]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** El año que se cierra: el que ya terminó si estamos en la ventana de cierre; si no, el vigente. */
    private function anioQueSeCierra(): ?int
    {
        return $this->anios->estadoCierreAnio()['anio_cerrado']->id
            ?? $this->anios->obtenerUltimoAnioEscolar()['data']?->id;
    }

    /**
     * Pasa los estudiantes activos de los cursos indicados a su curso próximo, todos a la vez:
     * un solo UPDATE con JOIN, así Quinto→Sexto no se encadena con Sexto→Séptimo. Ignora los
     * cursos sin curso próximo activo. $excluir: retirados en la confirmación, se quedan en su
     * curso. No se puede deshacer desde la app: el movimiento queda en el log
     * (`promocion de cursos`) por si hay que revertirlo a mano.
     */
    public function promover(array $idsCursos, array $excluir, int $idLog): array
    {
        try {
            $movimientos = DB::table('usuarios')
                ->when($excluir, fn ($q) => $q->whereNotIn('usuarios.id_user', $excluir))
                ->join('curso as c', 'c.id', '=', 'usuarios.id_curso')
                ->join('curso as p', 'p.id', '=', 'c.curso_proximo')
                ->whereIn('usuarios.id_curso', $idsCursos)
                ->where('c.activo', 1)
                ->where('p.activo', 1)
                ->where('usuarios.perfil', self::PERFIL_ESTUDIANTE)
                ->where('usuarios.estado', 'activo');

            $detalle = (clone $movimientos)->get(['usuarios.id_user', 'usuarios.id_curso as de', 'c.curso_proximo as a']);

            if ($detalle->isEmpty()) {
                return ['error' => true, 'message' => 'No hay estudiantes para promover en los cursos elegidos'];
            }

            $total = $movimientos->update(['usuarios.id_curso' => DB::raw('c.curso_proximo')]);

            Log::info('promocion de cursos', ['id_log' => $idLog, 'movimientos' => $detalle->toArray()]);

            $this->registrarPromocion($total, $idLog);

            return ['error' => false, 'message' => "Se promovieron {$total} estudiante(s)", 'data' => ['total' => $total]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deja constancia del año que se cerró: el que ya terminó si estamos en la ventana de
     * cierre; si se promueve antes de que termine, el vigente. Va aparte y no falla: los
     * estudiantes ya se movieron, y un error aquí solo apagaría el aviso de inicio.
     */
    private function registrarPromocion(int $total, int $idLog): void
    {
        try {
            $idAnio = $this->anioQueSeCierra();

            if ($idAnio) {
                DB::table('cursos_promociones')->insert([
                    'id_anio_escolar' => $idAnio,
                    'total' => $total,
                    'id_log' => $idLog,
                    'fecha' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('No se registró la promoción de cursos', ['message' => $e->getMessage(), 'total' => $total]);
        }
    }

    /** Deja al estudiante sin curso (solo si hoy está en este). */
    public function quitarEstudiante(int $idCurso, int $idEstudiante): array
    {
        try {
            $total = Usuario::where('id_user', $idEstudiante)
                ->where('id_curso', $idCurso)
                ->where('perfil', self::PERFIL_ESTUDIANTE)
                ->update(['id_curso' => null]);

            return $total
                ? ['error' => false, 'message' => 'Estudiante retirado del curso', 'data' => null]
                : ['error' => true, 'message' => 'El estudiante no está en este curso', 'status' => 404];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

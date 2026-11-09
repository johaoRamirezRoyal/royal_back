<?php

namespace App\Services\GestionAcademica;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\Dimension;
use App\Models\GestionAcademica\EscalaValoracion;
use App\Models\GestionAcademica\Logro;
use App\Models\GestionAcademica\LogroPlan;
use App\Models\GestionAcademica\LogroPlanHistorial;
use App\Models\GestionAcademica\Valoracion;
use App\Models\GestionAcademica\ValoracionLogro;
use App\Models\Usuarios\NivelAcademico;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Cursos\CursosServices;
use App\Services\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Notas cualitativas: escala de desempeños, dimensiones por grado, logros por grado y periodo
 * (no por asignatura) y la planilla del curso. Ver docs/contexto/modulos/notas.md.
 *
 * Califica solo el director de grupo del curso: por estudiante y dimensión, desempeño por logro,
 * desempeño final y observación. Los demás (99 o 149) ven en solo lectura. Se califica y corrige
 * entre fecha_inicio y fecha_limite_notas (o fecha_fin) del periodo.
 */
class NotasService extends Service
{
    public function __construct(private AnioEscolarServices $anios) {}

    // ─── Escala ─────────────────────────────────────────────────

    public function listarEscalas(): array
    {
        try {
            $escalas = EscalaValoracion::with('niveles:id')->orderBy('orden')->orderBy('id')->get()
                ->map(fn (EscalaValoracion $e) => $e->only(['id', 'nombre', 'abreviatura', 'descripcion', 'color', 'orden', 'activo'])
                    + ['niveles' => $e->niveles->pluck('id')->map(fn ($id) => (int) $id)->values()]);

            return ['error' => false, 'message' => 'Escalas de valoración', 'data' => $escalas];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarEscala(array $data, ?int $id = null): array
    {
        try {
            $escala = $id ? EscalaValoracion::find($id) : new EscalaValoracion();
            if (! $escala) {
                return ['error' => true, 'message' => 'No se encontró el desempeño'];
            }
            DB::transaction(function () use ($escala, $data) {
                $escala->fill($data)->save();
                // Sin niveles = escala general.
                $escala->niveles()->sync($data['niveles'] ?? []);
            });

            return ['error' => false, 'message' => $id ? 'Desempeño actualizado' : 'Desempeño creado', 'data' => $escala];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reordena lo arrastrado en Configuración académica (desempeños de una escala, o las
     * dimensiones): cada id recibe su posición (1, 2, 3...). Los que no vienen conservan su orden.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo  EscalaValoracion o Dimension
     * @param  array<int, int>  $ids
     */
    public function ordenar(string $modelo, array $ids): array
    {
        try {
            DB::transaction(function () use ($modelo, $ids) {
                foreach (array_values($ids) as $i => $id) {
                    $modelo::whereKey($id)->update(['orden' => $i + 1]);
                }
            });

            return ['error' => false, 'message' => 'Orden guardado'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Dimensiones y método por nivel (Configuración académica, 99) ─────────

    /**
     * Catálogo de unidades evaluables de un tipo (por defecto DIMENSION, así la pestaña de
     * Dimensiones no se llena de asignaturas) con los ids de los grados que se evalúan con cada una.
     */
    public function listarDimensiones(?string $tipo = null): array
    {
        try {
            $uso = DB::table('academico_grado_dimension')->get()->groupBy('id_dimension');
            $data = Dimension::where('tipo', $tipo ?? Dimension::DIMENSION)->orderBy('orden')->orderBy('nombre')->get()
                ->map(fn (Dimension $d) => $d->only(['id', 'nombre', 'descripcion', 'tipo', 'id_area', 'id_asignatura', 'orden', 'activo'])
                    + ['grados' => ($uso[$d->id] ?? collect())->pluck('id_grado')->map(fn ($id) => (int) $id)->values()]);

            return ['error' => false, 'message' => 'Dimensiones', 'data' => $data];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarDimension(array $data, ?int $id = null): array
    {
        try {
            $dimension = $id ? Dimension::find($id) : new Dimension();
            if (! $dimension) {
                return ['error' => true, 'message' => 'No se encontró la dimensión'];
            }
            $dimension->fill($data);
            // Unidad consistente (como el CHECK de la migración); hoy la API solo crea DIMENSION.
            if ($error = Dimension::errorDeTipo($dimension->tipo, $dimension->id_area, $dimension->id_asignatura)) {
                return ['error' => true, 'message' => $error];
            }
            $dimension->save();

            return ['error' => false, 'message' => $id ? 'Dimensión actualizada' : 'Dimensión creada', 'data' => $dimension];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reemplaza los grados que se evalúan con una dimensión (así una dimensión común se aplica a
     * varios de una vez). Quitar un grado no borra los logros que ya la usan: quedan por
     * reubicar y no dejan enviar ni aprobar hasta asignarles otra.
     *
     * @param  array<int, int>  $idsGrado
     */
    public function asignarGrados(int $idDimension, array $idsGrado): array
    {
        try {
            if (! Dimension::whereKey($idDimension)->exists()) {
                return ['error' => true, 'message' => 'No se encontró la dimensión'];
            }
            DB::transaction(function () use ($idDimension, $idsGrado) {
                DB::table('academico_grado_dimension')->where('id_dimension', $idDimension)->delete();
                DB::table('academico_grado_dimension')->insert(array_map(
                    fn ($id) => ['id_grado' => $id, 'id_dimension' => $idDimension],
                    array_values(array_unique($idsGrado)),
                ));
            });

            return ['error' => false, 'message' => 'Grados de la dimensión guardados'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Crea o actualiza la unidad ASIGNATURA de cada asignatura (nombre y activo; el orden de una
     * existente no se toca, las nuevas van al final en orden alfabético) y vincula a cada grado las
     * unidades de las asignaturas con carga activa en alguno de sus cursos activos. Idempotente; no
     * quita vínculos. Solo escribe en academico_dimension y academico_grado_dimension (InnoDB).
     */
    public function sincronizarUnidadesAsignatura(): array
    {
        try {
            $asignaturas = DB::table('academico_asignatura')->get(['id', 'nombre', 'activo']);

            $data = DB::transaction(function () use ($asignaturas) {
                $existentes = Dimension::where('tipo', Dimension::ASIGNATURA)->get()->keyBy('id_asignatura');
                $creadas = $actualizadas = 0;

                foreach ($asignaturas->filter(fn ($a) => $existentes->has($a->id)) as $a) {
                    $unidad = $existentes->get($a->id)->fill(['nombre' => mb_substr(trim((string) $a->nombre), 0, 80), 'activo' => (bool) $a->activo]);
                    if ($unidad->isDirty()) {
                        $unidad->save();
                        $actualizadas++;
                    }
                }

                $orden = (int) Dimension::max('orden');
                $nuevas = $asignaturas->reject(fn ($a) => $existentes->has($a->id))
                    ->sortBy(fn ($a) => Str::ascii(Str::lower((string) $a->nombre)));
                foreach ($nuevas as $a) {
                    Dimension::create([
                        'tipo' => Dimension::ASIGNATURA, 'id_asignatura' => $a->id,
                        'nombre' => mb_substr(trim((string) $a->nombre), 0, 80), 'activo' => (bool) $a->activo, 'orden' => ++$orden,
                    ]);
                    $creadas++;
                }

                $unidadDe = Dimension::where('tipo', Dimension::ASIGNATURA)->pluck('id', 'id_asignatura');
                $vinculos = DB::table('academico_carga_academica as c')
                    ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
                    ->join('curso as cu', 'cu.id', '=', 'c.id_curso')
                    ->where('c.activo', 1)->where('da.activo', 1)->where('cu.activo', 1)->whereNotNull('cu.id_grado')
                    ->distinct()->get(['cu.id_grado', 'da.id_asignatura'])
                    ->filter(fn ($p) => $unidadDe->has($p->id_asignatura))
                    ->map(fn ($p) => ['id_grado' => (int) $p->id_grado, 'id_dimension' => (int) $unidadDe[$p->id_asignatura]])
                    ->values()->all();

                return [
                    'creadas' => $creadas,
                    'actualizadas' => $actualizadas,
                    'vinculos_nuevos' => $vinculos ? DB::table('academico_grado_dimension')->insertOrIgnore($vinculos) : 0,
                ];
            });

            return ['error' => false, 'message' => "Unidades sincronizadas: {$data['creadas']} creadas, {$data['actualizadas']} actualizadas y {$data['vinculos_nuevos']} vínculos con grados nuevos", 'data' => $data];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarMetodosNivel(): array
    {
        try {
            return ['error' => false, 'message' => 'Métodos de calificación', 'data' => NivelAcademico::orderBy('id')->get(['id', 'nombre', 'metodo_calificacion', 'tipo_unidad', 'calificador'])];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Combinación válida de unidad y calificador de un nivel, o el error. AREA aún no se ofrece; una
     * DIMENSION nunca tiene carga académica, así que con dimensiones califica el director de grupo.
     */
    public static function errorDeModo(string $tipoUnidad, string $calificador): ?string
    {
        if ($tipoUnidad === Dimension::AREA) {
            return 'La evaluación por área aún no está disponible.';
        }
        if (! in_array($tipoUnidad, [Dimension::DIMENSION, Dimension::ASIGNATURA], true)) {
            return 'Tipo de unidad no válido: DIMENSION o ASIGNATURA.';
        }
        if (! in_array($calificador, [self::CALIFICA_DIRECTOR, self::CALIFICA_DOCENTE_ASIGNATURA], true)) {
            return 'Calificador no válido: DIRECTOR_GRUPO o DOCENTE_ASIGNATURA.';
        }
        if ($tipoUnidad === Dimension::DIMENSION && $calificador !== self::CALIFICA_DIRECTOR) {
            return 'Con dimensiones califica el director de grupo: una dimensión no tiene carga académica.';
        }

        return null;
    }

    /** Por qué no se puede cambiar la unidad del nivel (ya tiene logros o notas), o null. */
    private function usoDelNivel(int $idNivel): ?string
    {
        $cursos = Cursos::where('id_nivel', $idNivel)->get(['id', 'id_grado']);
        $logros = Logro::whereIn('id_grado', $cursos->pluck('id_grado')->filter()->unique())->count();
        $notas = Valoracion::whereIn('id_curso', $cursos->pluck('id'))->count();
        if ($logros === 0 && $notas === 0) {
            return null;
        }

        return "ya tiene {$logros} logro(s) y {$notas} nota(s) registradas con la unidad actual, y cambiarla las dejaría sin unidad";
    }

    /**
     * Cambia el método de calificación, la unidad evaluable (`tipo_unidad`) y/o el calificador de un
     * nivel. Lo que no viene se conserva. Al pasar a ASIGNATURA sincroniza las unidades.
     *
     * @param  array{metodo?: string, tipo_unidad?: string, calificador?: string}  $cambios
     */
    public function cambiarMetodoNivel(int $idNivel, array $cambios): array
    {
        try {
            $nivel = NivelAcademico::find($idNivel);
            if (! $nivel) {
                return ['error' => true, 'message' => 'No se encontró el nivel'];
            }
            $tipo = $cambios['tipo_unidad'] ?? $nivel->tipo_unidad ?? Dimension::DIMENSION;
            $calificador = $cambios['calificador'] ?? $nivel->calificador ?? self::CALIFICA_DIRECTOR;
            if ($error = self::errorDeModo($tipo, $calificador)) {
                return ['error' => true, 'message' => $error];
            }
            $cambiaTipo = $tipo !== ($nivel->tipo_unidad ?? Dimension::DIMENSION);
            if ($cambiaTipo && ($motivo = $this->usoDelNivel($nivel->id))) {
                return ['error' => true, 'message' => "No se puede cambiar la unidad evaluable de {$nivel->nombre}: {$motivo}."];
            }

            $nivel->update(array_filter(['metodo_calificacion' => $cambios['metodo'] ?? null]) + ['tipo_unidad' => $tipo, 'calificador' => $calificador]);

            $mensaje = "{$nivel->nombre}: calificación actualizada";
            if ($cambiaTipo && $tipo === Dimension::ASIGNATURA) {
                $sync = $this->sincronizarUnidadesAsignatura();
                $mensaje .= $sync['error'] ? ". No se pudieron sincronizar las asignaturas: {$sync['message']}" : ". {$sync['message']}";
            }

            return ['error' => false, 'message' => $mensaje, 'data' => $nivel->only(['id', 'nombre', 'metodo_calificacion', 'tipo_unidad', 'calificador'])];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Escala activa del nivel del curso; si el nivel no tiene desempeños propios, la general (sin niveles). */
    private function escalaDelNivel(?int $idNivel): Collection
    {
        $propia = $idNivel
            ? EscalaValoracion::where('activo', 1)->whereHas('niveles', fn ($q) => $q->where('nivel_academico.id', $idNivel))->orderBy('orden')->get()
            : collect();

        return $propia->isNotEmpty()
            ? $propia
            : EscalaValoracion::where('activo', 1)->doesntHave('niveles')->orderBy('orden')->get();
    }

    // ─── Periodos y acceso ──────────────────────────────────────

    /** Se califica desde que empieza el periodo hasta su fecha límite (o su fin). */
    public static function periodoEditable(PeriodoAcademico $periodo): bool
    {
        $hoy = Carbon::today()->toDateString();
        $limite = $periodo->fecha_limite_notas ?? $periodo->fecha_fin;

        return $hoy >= $periodo->fecha_inicio && $hoy <= $limite;
    }

    /**
     * Periodos del año escolar vigente. No filtra por `activo`: el cron
     * periodos:desactivar-vencidos los apaga al pasar fecha_fin, y aún pueden estar en
     * su plazo de corrección.
     */
    private function periodosDelAnio(): Collection
    {
        $anio = $this->anios->obtenerUltimoAnioEscolar()['data'] ?? null;
        if (! $anio) {
            return collect();
        }

        return PeriodoAcademico::where('id_anio_escolar', $anio->id)->orderBy('fecha_inicio')->get()
            ->map(fn (PeriodoAcademico $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'editable' => self::periodoEditable($p)]);
    }

    /** ¿El usuario es hoy director de grupo de ese curso (o de alguno de esos cursos activos)? */
    public function dirige(int $idUser, int|array $idsCurso): bool
    {
        return Cursos::whereIn('id', (array) $idsCurso)->where('activo', 1)->where('id_director', $idUser)->exists();
    }

    public const CALIFICA_DIRECTOR = 'DIRECTOR_GRUPO';
    public const CALIFICA_DOCENTE_ASIGNATURA = 'DOCENTE_ASIGNATURA';

    /** Regla pura: con DOCENTE_ASIGNATURA, quien tiene carga en la unidad; en cualquier otro caso (incluido null), el director de grupo. */
    public static function calificadorPermite(?string $calificador, bool $dirige, bool $tieneCarga): bool
    {
        return $calificador === self::CALIFICA_DOCENTE_ASIGNATURA ? $tieneCarga : $dirige;
    }

    /** Ids de las asignaturas en las que el docente tiene carga activa en ese curso (o esos cursos). */
    private function asignaturasConCarga(int $idUser, int|array $idsCurso): Collection
    {
        return DB::table('academico_carga_academica as c')
            ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
            ->whereIn('c.id_curso', (array) $idsCurso)->where('c.activo', 1)
            ->where('da.activo', 1)->where('da.id_docente', $idUser)
            ->distinct()->pluck('da.id_asignatura')->map(fn ($id) => (int) $id);
    }

    /**
     * "Tiene carga" en la unidad, dadas las asignaturas con carga del docente: DIMENSION nunca;
     * ASIGNATURA, la suya; AREA, alguna asignatura activa del área.
     */
    private static function cubre(Dimension $unidad, Collection $conCarga): bool
    {
        return match ($unidad->tipo) {
            Dimension::ASIGNATURA => $conCarga->contains((int) $unidad->id_asignatura),
            Dimension::AREA => $conCarga->isNotEmpty() && DB::table('academico_asignatura')
                ->where('id_area', $unidad->id_area)->where('activo', 1)->whereIn('id', $conCarga)->exists(),
            default => false,
        };
    }

    /**
     * Ids de las unidades del grado del curso (del tipo de su nivel) que el usuario puede calificar
     * en ese curso, según `calificadorPermite`: con DIRECTOR_GRUPO, todas si dirige el curso; con
     * DOCENTE_ASIGNATURA, las de sus asignaturas con carga en ese curso. No mira plazo ni plan.
     */
    public function unidadesCalificables(int $idUser, Cursos|int $curso): Collection
    {
        $curso = $curso instanceof Cursos ? $curso : Cursos::find($curso, ['id', 'id_nivel', 'id_grado']);
        if (! $curso || $curso->id_grado === null) {
            return collect();
        }
        $modo = self::modoDelNivel($curso->id_nivel);
        $porCarga = $modo['calificador'] === self::CALIFICA_DOCENTE_ASIGNATURA;
        $dirige = ! $porCarga && $this->dirige($idUser, $curso->id);
        $conCarga = $porCarga ? $this->asignaturasConCarga($idUser, $curso->id) : collect();

        return $this->dimensionesDe($curso->id_grado, $modo['tipo_unidad'])
            ->filter(fn (Dimension $u) => self::calificadorPermite($modo['calificador'], $dirige, self::cubre($u, $conCarga)))
            ->pluck('id')->values();
    }

    /** Cursos activos del grado, en orden académico, con su director de grupo. */
    private static function cursosDelGrado(int $idGrado): Collection
    {
        return Cursos::ordenarPorGrado(Cursos::with('director:id_user,nombre,apellido')
            ->where('activo', 1)->where('id_grado', $idGrado)
            ->get(['id', 'nombre', 'id_nivel', 'id_grado', 'id_director']));
    }

    private static function nombreDe(?Usuario $u): ?string
    {
        return $u ? (trim("{$u->nombre} {$u->apellido}") ?: null) : null;
    }

    /** Unidad evaluable y calificador del nivel (los valores por defecto si no hay nivel). */
    private static function modoDelNivel(?int $idNivel): array
    {
        $nivel = $idNivel ? NivelAcademico::find($idNivel, ['id', 'tipo_unidad', 'calificador']) : null;

        return [
            'tipo_unidad' => $nivel?->tipo_unidad ?? Dimension::DIMENSION,
            'calificador' => $nivel?->calificador ?? self::CALIFICA_DIRECTOR,
        ];
    }

    /**
     * La parte cualitativa (logros, planilla, consulta) aplica a los cursos cuyo nivel académico
     * califica CUALITATIVA o AMBAS (nivel_academico.metodo_calificacion) y que tienen grado (los
     * logros son por grado). Error listo para devolver, o null si aplica.
     */
    private function sinCualitativa(int $idCurso): ?array
    {
        $curso = Cursos::whereKey($idCurso)->with('nivel:id,metodo_calificacion')->first(['id', 'id_nivel', 'id_grado']);

        if (! NivelAcademico::califCualitativa($curso?->nivel?->metodo_calificacion)) {
            return ['error' => true, 'message' => 'El nivel de este curso se califica solo de forma cuantitativa: no tiene planilla cualitativa.'];
        }

        return $curso->id_grado === null
            ? ['error' => true, 'message' => 'Este curso no tiene grado: los logros se definen por grado. Asígnaselo en Cursos.']
            : null;
    }

    /** Curso activo con grado cuyo nivel califica cualitativamente (para filtrar listas). */
    private static function esCursoCualitativo(Cursos $curso): bool
    {
        return $curso->activo && $curso->id_grado !== null && NivelAcademico::califCualitativa($curso->nivel?->metodo_calificacion);
    }

    /** Cursos cualitativos activos (con grado y nivel cargados), en orden académico. */
    private static function cursosCualitativos(?callable $filtro = null): Collection
    {
        $q = Cursos::with(['grado:id,nombre', 'nivel:id,nombre,metodo_calificacion,tipo_unidad,calificador', 'director:id_user,nombre,apellido'])
            ->where('activo', 1)->whereNotNull('id_grado');
        if ($filtro) {
            $filtro($q);
        }

        return Cursos::ordenarPorGrado($q->get(['id', 'nombre', 'id_nivel', 'id_grado', 'id_director', 'activo'])
            ->filter(fn (Cursos $c) => self::esCursoCualitativo($c))->values());
    }

    /**
     * Cursos cualitativos que califica el docente, y los periodos del año: los que dirige en niveles
     * DIRECTOR_GRUPO y aquellos en que tiene carga activa en niveles DOCENTE_ASIGNATURA. Cada uno con
     * `unidades_calificables` (ids; puede venir vacío si sus asignaturas aún no están sincronizadas).
     */
    public function misCursos(int $idDocente): array
    {
        try {
            $conCarga = DB::table('academico_carga_academica as c')
                ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
                ->where('c.activo', 1)->where('da.activo', 1)->where('da.id_docente', $idDocente)
                ->distinct()->pluck('c.id_curso')->map(fn ($id) => (int) $id);

            $cursos = self::cursosCualitativos(fn ($q) => $q->where(fn ($q) => $q->where('id_director', $idDocente)->orWhereIn('id', $conCarga)))
                ->filter(fn (Cursos $c) => ($c->nivel?->calificador ?? self::CALIFICA_DIRECTOR) === self::CALIFICA_DOCENTE_ASIGNATURA
                    ? $conCarga->contains($c->id)
                    : (int) $c->id_director === $idDocente)
                ->map(fn (Cursos $c) => [
                    'id_curso' => $c->id,
                    'curso' => $c->nombre,
                    'grado' => $c->grado?->nombre,
                    'calificador' => $c->nivel?->calificador ?? self::CALIFICA_DIRECTOR,
                    'unidades_calificables' => $this->unidadesCalificables($idDocente, $c),
                ])
                ->values();

            return ['error' => false, 'message' => 'Tus cursos', 'data' => ['cursos' => $cursos, 'periodos' => $this->periodosDelAnio()]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Catálogo para la consulta: cursos cualitativos activos y periodos del año. */
    public function filtrosConsulta(): array
    {
        try {
            $cursos = self::cursosCualitativos()
                ->map(fn (Cursos $c) => ['id' => $c->id, 'nombre' => $c->nombre, 'nivel' => $c->nivel ? ['id' => $c->nivel->id, 'nombre' => $c->nivel->nombre] : null])
                ->values();

            return ['error' => false, 'message' => 'Filtros', 'data' => ['cursos' => $cursos, 'periodos' => $this->periodosDelAnio()]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Planilla ───────────────────────────────────────────────

    /**
     * Estudiantes activos que hoy están en el curso, más los que ya tienen nota en él en ese
     * periodo aunque se hayan movido (en_curso = false).
     */
    private function estudiantesDePlanilla(int $idCurso, int $idPeriodo): Collection
    {
        $conNota = Valoracion::where('id_curso', $idCurso)->where('id_periodo_academico', $idPeriodo)->pluck('id_estudiante');

        return self::estudiantesDelCurso($idCurso, $conNota)
            ->get(['id_user', 'nombre', 'apellido', 'documento', 'foto_carnet', 'id_curso', 'estado'])
            ->map(fn ($u) => [
                'id_user' => $u->id_user,
                'nombre' => $u->nombre,
                'apellido' => $u->apellido,
                'documento' => $u->documento,
                'foto_carnet' => $u->foto_carnet,
                'en_curso' => (int) $u->id_curso === $idCurso && $u->estado === 'activo',
            ]);
    }

    /** Estudiantes activos del curso hoy, más los que ya tienen nota en él (`$idsConNota`), por apellido. */
    private static function estudiantesDelCurso(int $idCurso, Collection $idsConNota)
    {
        return Usuario::where('perfil', CursosServices::PERFIL_ESTUDIANTE)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('id_curso', $idCurso)->where('estado', 'activo'))
                ->orWhereIn('id_user', $idsConNota))
            ->orderBy('apellido')->orderBy('nombre');
    }

    private function logrosDe(int $idGrado, int $idPeriodo): Collection
    {
        return Logro::where('id_grado', $idGrado)->where('id_periodo_academico', $idPeriodo)
            ->orderBy('orden')->orderBy('id')
            ->get(['id', 'id_dimension', 'descripcion', 'orden']);
    }

    /**
     * Unidades activas con las que se evalúa el grado, en su orden. Con `$tipo` (el `tipo_unidad`
     * del nivel), solo las de ese tipo: en un plan no se mezclan dimensiones y asignaturas.
     */
    private function dimensionesDe(int $idGrado, ?string $tipo = null): Collection
    {
        return Dimension::where('activo', 1)
            ->whereIn('id', DB::table('academico_grado_dimension')->where('id_grado', $idGrado)->pluck('id_dimension'))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('orden')->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'tipo', 'id_area', 'id_asignatura']);
    }

    /** Califica solo el director de grupo del curso, en plazo y con los logros aprobados; los demás ven. */
    public function planilla(array $actor, int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;

            $estudiantes = $this->estudiantesDePlanilla($idCurso, $idPeriodo);
            $valoraciones = Valoracion::with('logros:id_valoracion,id_logro,id_escala')
                ->where('id_periodo_academico', $idPeriodo)->whereIn('id_estudiante', $estudiantes->pluck('id_user'))
                ->get()->groupBy('id_estudiante');

            $estudiantes = $estudiantes->map(function ($e) use ($valoraciones) {
                $suyas = $valoraciones->get($e['id_user'], collect());

                return $e + [
                    // id_dimension → desempeño final y observación de la dimensión.
                    'dimensiones' => $suyas->mapWithKeys(fn ($v) => [$v->id_dimension => ['id_escala' => $v->id_escala, 'observacion' => $v->observacion]]),
                    // id_logro → desempeño.
                    'logros' => $suyas->flatMap(fn ($v) => $v->logros)->mapWithKeys(fn ($l) => [$l->id_logro => $l->id_escala]),
                ];
            })->values();

            $plan = $this->datosPlan($curso, $periodo, $actor);
            $modo = self::modoDelNivel($curso->id_nivel);
            $esDirector = $curso->id_director !== null && (int) $curso->id_director === $actor['id'];
            // Se califica en plazo, con los logros aprobados y solo las unidades que le tocan a quien pide.
            $abierta = self::periodoEditable($periodo) && $plan['estado'] === LogroPlan::APROBADO;
            $calificables = $this->unidadesCalificables($actor['id'], $curso);
            $dimensiones = collect($plan['dimensiones'])
                ->map(fn (array $u) => ['editable' => $abierta && $calificables->contains($u['id'])] + $u)->values();

            return ['error' => false, 'message' => 'Planilla', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre],
                'director' => self::nombreDe($curso->director),
                'periodo' => [
                    'id' => $periodo->id,
                    'nombre' => $periodo->nombre,
                    'fecha_limite_notas' => $periodo->fecha_limite_notas ?? $periodo->fecha_fin,
                ],
                'periodo_editable' => self::periodoEditable($periodo),
                'es_director' => $esDirector,
                'tipo_unidad' => $modo['tipo_unidad'],
                'calificador' => $modo['calificador'],
                // Con DIRECTOR_GRUPO, como siempre (también sin unidades); con DOCENTE_ASIGNATURA, si
                // alguna unidad es editable.
                'editable' => $modo['calificador'] === self::CALIFICA_DOCENTE_ASIGNATURA
                    ? $dimensiones->contains('editable', true)
                    : $esDirector && $abierta,
                'escala' => $this->escalaDelNivel($curso->id_nivel)->map->only(['id', 'nombre', 'abreviatura', 'descripcion', 'color'])->values(),
                'logros' => $plan['logros'],
                // editable aquí = puede calificar esa unidad en este curso (en plazo y con el plan aprobado).
                'dimensiones' => $dimensiones,
                // Estado de los logros, historial y acciones de quien pide. unidades_editables: las
                // unidades cuyos logros puede editar (plan.dimensiones[].editable de verPlan).
                'plan' => [
                    'estado' => $plan['estado'], 'grado' => $plan['grado'], 'compartido' => $plan['compartido'],
                    'historial' => $plan['historial'], 'puede' => $plan['puede'],
                    'unidades_editables' => collect($plan['dimensiones'])->where('editable', true)->pluck('id')->values(),
                ],
                'estudiantes' => $estudiantes,
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function validarVentana(int $idPeriodo): ?array
    {
        $periodo = PeriodoAcademico::find($idPeriodo);
        if (! $periodo) {
            return ['error' => true, 'message' => 'Periodo no encontrado'];
        }
        if (! self::periodoEditable($periodo)) {
            return ['error' => true, 'message' => 'El periodo está cerrado para notas: ya pasó su fecha límite o aún no empieza.'];
        }

        return null;
    }

    // ─── Plan de logros (estado y seguimiento) ──────────────────
    //
    // Los logros son del grado (todos sus cursos los comparten) y los definen en conjunto los
    // directores de grupo del grado y el coordinador / asistente del nivel (opciones 150 y 151).
    // Estados: BORRADOR → EN_REVISION → APROBADO, o DEVUELTO (vuelve a editarse). Solo se
    // califica con el plan APROBADO; para cambiarlo, quien aprueba lo reabre. `$actor` lo arma
    // NotasController::actor():
    //   ['id' => int, 'gestiona' => bool (150), 'aprueba' => bool (151), 'niveles' => ?int[] (null = todos)]

    /** Niveles académicos (nivel_academico.id) del usuario, por usuarios.id_nivel / id_nivel_2. */
    public function nivelesDeUsuario(int $idUser): array
    {
        $u = Usuario::with(['nivelRelacion', 'nivel2Relacion'])->find($idUser);

        return collect([$u?->nivelRelacion, $u?->nivel2Relacion])
            ->flatMap(fn ($nivel) => DocenteHorarioService::idsNivelAcademicoParaNivel($nivel))
            ->unique()->values()->all();
    }

    /**
     * Qué es quien pide respecto de los logros del grado del curso. 'docente': según el calificador
     * del nivel, director de grupo de alguno de sus cursos (DIRECTOR_GRUPO) o con carga activa en
     * alguno (DOCENTE_ASIGNATURA). 'gestiona' / 'aprueba': 150 / 151 en su nivel. 'modo_unidad':
     * DIMENSION o ASIGNATURA (lo usa acciones()); 'calificador' va para unidadesEditables().
     */
    private function rol(array $actor, Cursos $curso): array
    {
        $enNivel = $actor['niveles'] === null || in_array($curso->id_nivel, $actor['niveles'], true);
        $modo = self::modoDelNivel($curso->id_nivel);
        $cursosGrado = self::cursosDelGrado($curso->id_grado)->pluck('id')->all();

        return [
            'docente' => $modo['calificador'] === self::CALIFICA_DOCENTE_ASIGNATURA
                ? $this->asignaturasConCarga($actor['id'], $cursosGrado)->isNotEmpty()
                : $this->dirige($actor['id'], $cursosGrado),
            'gestiona' => $enNivel && ($actor['gestiona'] || $actor['aprueba']),
            'aprueba' => $enNivel && $actor['aprueba'],
            'modo_unidad' => $modo['tipo_unidad'] === Dimension::ASIGNATURA ? Dimension::ASIGNATURA : Dimension::DIMENSION,
            'calificador' => $modo['calificador'],
        ];
    }

    /**
     * Ids de las unidades del grado cuyos logros puede editar quien pide (aparte del estado del
     * plan, que decide acciones()): quien gestiona, todas; en modo DIMENSION, todas para el docente
     * del grado; en modo ASIGNATURA, el docente solo las de sus asignaturas con carga en algún curso
     * del grado (con calificador DIRECTOR_GRUPO, el director edita todas).
     */
    private function unidadesEditables(array $actor, Cursos $curso, array $rol, Collection $unidades): Collection
    {
        if ($rol['gestiona'] || ($rol['docente'] && ($rol['modo_unidad'] !== Dimension::ASIGNATURA || $rol['calificador'] !== self::CALIFICA_DOCENTE_ASIGNATURA))) {
            return $unidades->pluck('id')->values();
        }
        if (! $rol['docente']) {
            return collect();
        }
        $conCarga = $this->asignaturasConCarga($actor['id'], self::cursosDelGrado($curso->id_grado)->pluck('id')->all());

        return $unidades->filter(fn (Dimension $u) => self::cubre($u, $conCarga))->pluck('id')->values();
    }

    /** Los logros se pueden trabajar hasta la fecha límite del periodo (también antes de que empiece). */
    private static function periodoAbiertoParaLogros(PeriodoAcademico $periodo): bool
    {
        return Carbon::today()->toDateString() <= ($periodo->fecha_limite_notas ?? $periodo->fecha_fin);
    }

    /**
     * Acciones permitidas según el estado, el rol y el plazo. En modo ASIGNATURA
     * (`$rol['modo_unidad']`) solo quien gestiona envía a revisión: así un docente no cierra el plan
     * antes de que los demás carguen los suyos. Sin esa clave, como en modo DIMENSION.
     */
    public static function acciones(string $estado, array $rol, bool $abierto): array
    {
        $editable = in_array($estado, [LogroPlan::BORRADOR, LogroPlan::DEVUELTO], true);
        $participa = $rol['docente'] || $rol['gestiona'];
        $enviaSoloGestor = ($rol['modo_unidad'] ?? Dimension::DIMENSION) === Dimension::ASIGNATURA;

        return [
            // En revisión solo quien aprueba ajusta los logros.
            'editar' => $abierto && (($editable && $participa) || ($estado === LogroPlan::EN_REVISION && $rol['aprueba'])),
            'enviar' => $abierto && $editable && ($enviaSoloGestor ? $rol['gestiona'] : $participa),
            'aprobar' => $estado === LogroPlan::EN_REVISION && $rol['aprueba'],
            'devolver' => $estado === LogroPlan::EN_REVISION && $rol['aprueba'],
            'reabrir' => $abierto && $estado === LogroPlan::APROBADO && $rol['aprueba'],
        ];
    }

    private function buscarPlan(int $idGrado, int $idPeriodo): LogroPlan
    {
        return LogroPlan::firstOrNew(['id_grado' => $idGrado, 'id_periodo_academico' => $idPeriodo]);
    }

    /** Curso (con su grado, nivel y director) y periodo; null si alguno no existe. */
    private static function cursoYPeriodo(int $idCurso, int $idPeriodo): ?array
    {
        $curso = Cursos::with(['grado:id,nombre', 'director:id_user,nombre,apellido'])->find($idCurso, ['id', 'nombre', 'id_nivel', 'id_grado', 'id_director']);
        $periodo = PeriodoAcademico::find($idPeriodo);

        return $curso && $periodo ? [$curso, $periodo] : null;
    }

    private function registrar(LogroPlan $plan, int $idUsuario, string $accion, ?string $detalle = null): void
    {
        LogroPlanHistorial::create(['id_plan' => $plan->id, 'id_usuario' => $idUsuario, 'accion' => $accion, 'detalle' => $detalle, 'fecha' => now()]);
    }

    /**
     * Plan de logros del grado del curso: estado, si lo comparten varios cursos, logros, unidades
     * (cada una con `editable`: si quien pide puede editar sus logros, aparte del estado del plan),
     * historial (lo más reciente primero) y qué puede hacer quien pide.
     */
    private function datosPlan(Cursos $curso, PeriodoAcademico $periodo, array $actor): array
    {
        $rol = $this->rol($actor, $curso);
        $unidades = $this->dimensionesDe($curso->id_grado, self::modoDelNivel($curso->id_nivel)['tipo_unidad']);
        $editables = $this->unidadesEditables($actor, $curso, $rol, $unidades);
        $plan = $this->buscarPlan($curso->id_grado, $periodo->id);
        $historial = $plan->exists
            ? $plan->historial()->with('usuario:id_user,nombre,apellido')->orderByDesc('id')->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'accion' => $h->accion,
                    'detalle' => $h->detalle,
                    'fecha' => $h->fecha,
                    'usuario' => self::nombreDe($h->usuario) ?? 'Usuario',
                ])
            : collect();

        return [
            'estado' => $plan->estado,
            // El grado ("Cuarto") cuyos cursos comparten estos logros.
            'grado' => (string) $curso->grado?->nombre,
            'compartido' => self::cursosDelGrado($curso->id_grado)->count() > 1,
            'logros' => $this->logrosDe($curso->id_grado, $periodo->id),
            // Las unidades del grado (del tipo de su nivel): cada logro pertenece a una.
            'dimensiones' => $unidades->map(fn (Dimension $u) => $u->toArray() + ['editable' => $editables->contains($u->id)])->values(),
            'historial' => $historial,
            'puede' => self::acciones($plan->estado, $rol, self::periodoAbiertoParaLogros($periodo)),
        ];
    }

    /** Lo que ve cualquiera con acceso al grado (director de grupo, 150/151 de su nivel, 99, 149). */
    public function verPlan(array $actor, int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;

            return ['error' => false, 'message' => 'Plan de logros', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre],
                'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre],
            ] + $this->datosPlan($curso, $periodo, $actor)];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** ¿Participa en los logros del grado del curso? Director de grupo del grado, o 150/151 de su nivel. (99/149 lo decide el controller.) */
    public function participa(array $actor, int $idCurso): bool
    {
        $curso = Cursos::find($idCurso, ['id', 'id_nivel', 'id_grado']);
        if (! $curso || $curso->id_grado === null) {
            return false;
        }
        $rol = $this->rol($actor, $curso);

        return $rol['docente'] || $rol['gestiona'];
    }

    /**
     * Qué hace guardarLogros con la lista enviada, según las unidades que quien guarda puede editar
     * (`$editables`). Pura (test: CambiosDeLogrosTest).
     *
     * - Un logro de una unidad no editable que viene sin cambios se ignora (el frontend manda la
     *   lista completa); si viene cambiado, movido o nuevo en esa unidad, se rechaza.
     * - Se eliminan solo los existentes de unidades editables que no vienen: los de los demás
     *   docentes se conservan.
     *
     * @param  array<int, array{id_dimension: int, descripcion: string}>  $existentes  por id de logro
     * @param  array<int, array{id?: int|null, id_dimension: int, descripcion: string}>  $enviados
     * @param  array<int, int>  $editables  ids de unidad
     * @return array{rechazada: ?int, ignorar: array<int, int>, eliminar: array<int, int>}
     *   rechazada: la primera unidad no editable que se intentó tocar; ignorar: índices de
     *   `$enviados` que no se tocan (también ids ajenos al grado y periodo); eliminar: ids de logro.
     */
    public static function cambiosDeLogros(array $existentes, array $enviados, array $editables): array
    {
        $puede = fn ($idUnidad) => in_array((int) $idUnidad, array_map('intval', $editables), true);
        $ignorar = $vienen = [];

        foreach (array_values($enviados) as $i => $l) {
            $id = $l['id'] ?? null;
            $destino = (int) $l['id_dimension'];
            if ($id !== null) {
                if (! isset($existentes[$id])) {
                    $ignorar[] = $i;
                    continue;
                }
                $vienen[] = (int) $id;
                $actual = $existentes[$id];
                if (! $puede($actual['id_dimension'])) {
                    $igual = $destino === (int) $actual['id_dimension'] && trim((string) $l['descripcion']) === trim((string) $actual['descripcion']);
                    if ($igual) {
                        $ignorar[] = $i;
                        continue;
                    }

                    return ['rechazada' => (int) $actual['id_dimension'], 'ignorar' => [], 'eliminar' => []];
                }
            }
            if (! $puede($destino)) {
                return ['rechazada' => $destino, 'ignorar' => [], 'eliminar' => []];
            }
        }

        $eliminar = array_values(array_filter(
            array_map('intval', array_keys($existentes)),
            fn ($id) => ! in_array($id, $vienen, true) && $puede($existentes[$id]['id_dimension']),
        ));

        return ['rechazada' => null, 'ignorar' => $ignorar, 'eliminar' => $eliminar];
    }

    /**
     * Guarda los logros del grado en el periodo: los que traen id se actualizan, los nuevos se
     * crean y los que no vienen se borran, pero solo en las unidades que quien guarda puede editar
     * (cambiosDeLogros). No se quita un logro que ya tiene notas. Cada cambio queda en el historial.
     *
     * @param  array<int, array{id?: int|null, id_dimension: int, descripcion: string}>  $logros
     */
    public function guardarLogros(array $actor, int $idCurso, int $idPeriodo, array $logros): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            $idGrado = $curso->id_grado;
            $plan = $this->buscarPlan($idGrado, $idPeriodo);
            $rol = $this->rol($actor, $curso);
            if (! self::acciones($plan->estado, $rol, self::periodoAbiertoParaLogros($periodo))['editar']) {
                return ['error' => true, 'message' => $plan->estado === LogroPlan::APROBADO
                    ? 'Los logros están aprobados. Para cambiarlos, quien aprueba debe reabrirlos.'
                    : 'No puedes editar estos logros en su estado actual o ya pasó el plazo del periodo.'];
            }

            // Todo indicador pertenece a una unidad del grado (obligatoria), del tipo de su nivel.
            $porAsignatura = $rol['modo_unidad'] === Dimension::ASIGNATURA;
            $dimensiones = $this->dimensionesDe($idGrado, self::modoDelNivel($curso->id_nivel)['tipo_unidad'])->keyBy('id');
            if ($dimensiones->isEmpty()) {
                return ['error' => true, 'message' => $porAsignatura
                    ? 'Este grado no tiene asignaturas vinculadas: sincroniza las unidades en Configuración académica antes de definir logros.'
                    : 'Este grado no tiene dimensiones: asígnaselas en Configuración académica → Dimensiones antes de definir logros.'];
            }
            foreach ($logros as $l) {
                if (! $dimensiones->has($l['id_dimension'] ?? null)) {
                    return ['error' => true, 'message' => $porAsignatura
                        ? 'Cada logro debe pertenecer a una de las asignaturas del grado.'
                        : 'Cada logro debe pertenecer a una de las dimensiones del grado.'];
                }
            }

            $existentes = Logro::where('id_grado', $idGrado)->where('id_periodo_academico', $idPeriodo)->get()->keyBy('id');
            $editables = $this->unidadesEditables($actor, $curso, $rol, $dimensiones->values());
            // Los de una unidad que el grado ya no tiene (por reubicar) los mueve quien edita todo el grado.
            if ($rol['gestiona'] || ! $porAsignatura) {
                $editables = $editables->merge($existentes->pluck('id_dimension')->diff($dimensiones->keys()))->unique();
            }

            $cambios = self::cambiosDeLogros(
                $existentes->map(fn (Logro $l) => ['id_dimension' => (int) $l->id_dimension, 'descripcion' => $l->descripcion])->all(),
                $logros,
                $editables->map(fn ($id) => (int) $id)->all(),
            );
            if ($cambios['rechazada'] !== null) {
                $nombre = $dimensiones->get($cambios['rechazada'])?->nombre ?? Dimension::find($cambios['rechazada'])?->nombre ?? 'esa unidad';

                return ['error' => true, 'message' => $porAsignatura
                    ? "No puedes editar los logros de «{$nombre}»: solo los de las asignaturas en las que tienes carga en este grado."
                    : "No puedes editar los logros de «{$nombre}»."];
            }

            // Quitar un logro borraría sus notas (cascada): se rechaza todo el guardado.
            $conNotas = ValoracionLogro::whereIn('id_logro', $cambios['eliminar'])
                ->selectRaw('id_logro, count(*) as n')->groupBy('id_logro')->pluck('n', 'id_logro');
            if ($conNotas->isNotEmpty()) {
                $idLogro = (int) $conNotas->keys()->first();
                $n = (int) $conNotas->first();

                return ['error' => true, 'message' => "No se puede quitar el logro «{$existentes->get($idLogro)?->descripcion}»: ya tiene {$n} "
                    . ($n === 1 ? 'nota registrada' : 'notas registradas') . ' y se perderían. Edita su texto en lugar de quitarlo, o quita primero esas notas en la planilla.'];
            }

            DB::transaction(function () use ($actor, $plan, $idGrado, $idPeriodo, $logros, $dimensiones, $existentes, $cambios) {
                $plan->save();

                foreach (array_values($logros) as $orden => $l) {
                    if (in_array($orden, $cambios['ignorar'], true)) {
                        continue;
                    }
                    $descripcion = trim($l['descripcion']);
                    $idDimension = (int) $l['id_dimension'];
                    $enDimension = ' (' . $dimensiones->get($idDimension)?->nombre . ')';
                    $logro = isset($l['id']) ? $existentes->get($l['id']) : null;
                    if (! $logro) {
                        $logro = new Logro(['id_grado' => $idGrado, 'id_periodo_academico' => $idPeriodo, 'id_docente' => $actor['id']]);
                        $this->registrar($plan, $actor['id'], 'LOGRO_CREADO', $descripcion . $enDimension);
                    } elseif ($logro->descripcion !== $descripcion || $logro->id_dimension !== $idDimension) {
                        $this->registrar($plan, $actor['id'], 'LOGRO_EDITADO', "«{$logro->descripcion}» → «{$descripcion}»{$enDimension}");
                    }
                    $logro->fill(['descripcion' => $descripcion, 'id_dimension' => $idDimension, 'orden' => $orden])->save();
                }

                foreach ($cambios['eliminar'] as $idLogro) {
                    $this->registrar($plan, $actor['id'], 'LOGRO_ELIMINADO', $existentes->get($idLogro)?->descripcion);
                }
                Logro::whereIn('id', $cambios['eliminar'])->delete();
            });

            return ['error' => false, 'message' => 'Logros guardados', 'data' => $this->logrosDe($idGrado, $idPeriodo)];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cambia el estado del plan: enviar (a revisión), aprobar, devolver (con comentario) o
     * reabrir (un aprobado vuelve a BORRADOR; las notas ya puestas se conservan).
     */
    public function cambiarEstado(array $actor, int $idCurso, int $idPeriodo, string $accion, ?string $comentario): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            $plan = $this->buscarPlan($curso->id_grado, $idPeriodo);
            if (! (self::acciones($plan->estado, $this->rol($actor, $curso), self::periodoAbiertoParaLogros($periodo))[$accion] ?? false)) {
                return ['error' => true, 'message' => 'Esta acción no está disponible para ti en el estado actual de los logros.'];
            }
            $comentario = trim((string) $comentario) ?: null;
            if ($accion === 'devolver' && $comentario === null) {
                return ['error' => true, 'message' => 'Indica qué hay que corregir al devolver los logros.'];
            }
            if (in_array($accion, ['enviar', 'aprobar'], true)) {
                $logros = $this->logrosDe($curso->id_grado, $idPeriodo);
                if ($accion === 'enviar' && $logros->isEmpty()) {
                    return ['error' => true, 'message' => 'Define al menos un logro antes de enviarlos a revisión.'];
                }
                // Un logro con una dimensión que el grado ya no tiene no se califica.
                $validas = $this->dimensionesDe($curso->id_grado, self::modoDelNivel($curso->id_nivel)['tipo_unidad'])->pluck('id');
                if ($logros->contains(fn ($l) => ! $validas->contains($l->id_dimension))) {
                    return ['error' => true, 'message' => 'Hay logros sin una dimensión del grado: asígnales una antes de continuar.'];
                }
            }

            [$estado, $registro, $mensaje] = match ($accion) {
                'enviar' => [LogroPlan::EN_REVISION, 'ENVIADO', 'Logros enviados a revisión'],
                'aprobar' => [LogroPlan::APROBADO, 'APROBADO', 'Logros aprobados: ya se puede calificar'],
                'devolver' => [LogroPlan::DEVUELTO, 'DEVUELTO', 'Logros devueltos para corregir'],
                'reabrir' => [LogroPlan::BORRADOR, 'REABIERTO', 'Logros reabiertos: no se califica hasta aprobarlos de nuevo'],
            };

            DB::transaction(function () use ($plan, $estado, $actor, $registro, $comentario) {
                $plan->estado = $estado;
                $plan->save();
                $this->registrar($plan, $actor['id'], $registro, $comentario);
            });

            return ['error' => false, 'message' => $mensaje];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Grados cualitativos del nivel de quien gestiona (o todos, con niveles null), con sus cursos,
     * directores de grupo, cantidad de logros y estado del plan en el periodo. Para la página de
     * Logros (150/151).
     */
    public function gradosDelNivel(array $actor, ?int $idPeriodo): array
    {
        try {
            $periodos = $this->periodosDelAnio();
            $idPeriodo ??= ($periodos->firstWhere('editable', true) ?? $periodos->last())['id'] ?? null;

            $cursos = self::cursosCualitativos(fn ($q) => $q->when($actor['niveles'] !== null, fn ($q) => $q->whereIn('id_nivel', $actor['niveles'] ?: [0])));
            $estados = $idPeriodo ? LogroPlan::where('id_periodo_academico', $idPeriodo)->pluck('estado', 'id_grado') : collect();
            $logros = $idPeriodo
                ? Logro::where('id_periodo_academico', $idPeriodo)->selectRaw('id_grado, count(*) as n')->groupBy('id_grado')->pluck('n', 'id_grado')
                : collect();

            // Una fila por grado, en orden académico. id_curso es el primero del grado: con él se abre su plan.
            $data = $cursos->groupBy('id_grado')->map(fn (Collection $cs, $idGrado) => [
                'id_grado' => (int) $idGrado,
                'id_curso' => $cs->first()->id,
                'grado' => $cs->first()->grado?->nombre,
                'nivel' => $cs->first()->nivel?->nombre,
                // Para el buscador y el detalle.
                'cursos' => $cs->pluck('nombre')->values(),
                'directores' => $cs->map(fn ($c) => self::nombreDe($c->director))->filter()->unique()->values(),
                'logros' => (int) ($logros[$idGrado] ?? 0),
                'estado' => $estados[$idGrado] ?? LogroPlan::BORRADOR,
            ])->values();

            return ['error' => false, 'message' => 'Grados del nivel', 'data' => [
                'grados' => $data,
                'periodos' => $periodos,
                'id_periodo' => $idPeriodo,
                // Para que la página explique bien una lista vacía: todos los niveles (99 / Super
                // Admin), los del usuario, o ninguno (usuario sin nivel).
                'alcance' => $actor['niveles'] === null ? 'todos' : ($actor['niveles'] ? 'nivel' : 'sin_nivel'),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Guarda la planilla. Por estudiante y dimensión: desempeño final, observación y desempeño por
     * logro. Una dimensión sin nada de eso queda sin nota (se borra la fila); las dimensiones que
     * no vienen no se tocan.
     *
     * @param  array<int, array{id_estudiante: int, dimensiones: array<int, array{id_dimension: int, id_escala?: int|null, observacion?: string|null, logros?: array<int, array{id_logro: int, id_escala: int}>}>}>  $estudiantes
     */
    public function guardarPlanilla(int $idDocente, int $idCurso, int $idPeriodo, array $estudiantes): array
    {
        if ($error = $this->sinCualitativa($idCurso) ?? $this->validarVentana($idPeriodo)) {
            return $error;
        }
        $curso = Cursos::find($idCurso, ['id', 'id_nivel', 'id_grado']);
        if (! $curso) {
            return ['error' => true, 'message' => 'Curso no encontrado'];
        }
        if ($this->buscarPlan($curso->id_grado, $idPeriodo)->estado !== LogroPlan::APROBADO) {
            return ['error' => true, 'message' => 'Los logros de este periodo aún no están aprobados: no se puede calificar todavía.'];
        }

        try {
            $escalaValida = $this->escalaDelNivel($curso->id_nivel)->pluck('id')->flip();
            $dimensionesValidas = $this->dimensionesDe($curso->id_grado, self::modoDelNivel($curso->id_nivel)['tipo_unidad'])->pluck('id')->flip();
            // id_logro → id_dimension: cada logro se califica dentro de su dimensión.
            $logroDimension = $this->logrosDe($curso->id_grado, $idPeriodo)->pluck('id_dimension', 'id');
            $alumnosValidos = $this->estudiantesDePlanilla($idCurso, $idPeriodo)->pluck('id_user')->flip();

            // Quién califica cada unidad depende del nivel (unidadesCalificables → calificadorPermite).
            $unidades = Dimension::whereIn('id', $dimensionesValidas->keys())->get(['id', 'nombre'])->keyBy('id');
            $calificables = $this->unidadesCalificables($idDocente, $curso)->flip();

            foreach ($estudiantes as $e) {
                if (! $alumnosValidos->has($e['id_estudiante'])) {
                    return ['error' => true, 'message' => 'Uno de los estudiantes no pertenece a este curso'];
                }
                foreach ($e['dimensiones'] ?? [] as $d) {
                    if (! $dimensionesValidas->has($d['id_dimension'])) {
                        return ['error' => true, 'message' => 'Una dimensión no corresponde al grado de este curso'];
                    }
                    if (! $calificables->has($d['id_dimension'])) {
                        return ['error' => true, 'message' => 'No te corresponde calificar «' . $unidades->get($d['id_dimension'])?->nombre . '» en este curso.'];
                    }
                    $escalas = array_merge([$d['id_escala'] ?? null], array_column($d['logros'] ?? [], 'id_escala'));
                    foreach (array_filter($escalas) as $idEscala) {
                        if (! $escalaValida->has($idEscala)) {
                            return ['error' => true, 'message' => 'Un desempeño no corresponde a la escala de este nivel'];
                        }
                    }
                    foreach ($d['logros'] ?? [] as $l) {
                        if ((int) ($logroDimension[$l['id_logro']] ?? 0) !== (int) $d['id_dimension']) {
                            return ['error' => true, 'message' => 'Un logro no corresponde a esta dimensión y periodo'];
                        }
                    }
                }
            }

            DB::transaction(function () use ($idDocente, $idCurso, $idPeriodo, $estudiantes) {
                foreach ($estudiantes as $e) {
                    foreach ($e['dimensiones'] ?? [] as $d) {
                        $observacion = trim((string) ($d['observacion'] ?? '')) ?: null;
                        $logros = $d['logros'] ?? [];
                        $clave = ['id_estudiante' => $e['id_estudiante'], 'id_dimension' => $d['id_dimension'], 'id_periodo_academico' => $idPeriodo];

                        if (empty($d['id_escala']) && $observacion === null && empty($logros)) {
                            Valoracion::where($clave)->delete();
                            continue;
                        }

                        $v = Valoracion::firstOrNew($clave);
                        $v->fill([
                            'id_curso' => $idCurso,
                            'id_escala' => $d['id_escala'] ?? null,
                            'observacion' => $observacion,
                            'id_docente' => $idDocente,
                        ])->save();

                        ValoracionLogro::where('id_valoracion', $v->id)->delete();
                        if ($logros) {
                            ValoracionLogro::insert(array_map(fn ($l) => [
                                'id_valoracion' => $v->id, 'id_logro' => $l['id_logro'], 'id_escala' => $l['id_escala'],
                            ], $logros));
                        }
                    }
                }
            });

            return ['error' => false, 'message' => 'Notas guardadas'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Consulta (solo lectura) ────────────────────────────────

    /** Matriz estudiantes × dimensiones de un curso en un periodo, con el desempeño final de cada dimensión. */
    public function consulta(int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            $curso = Cursos::find($idCurso, ['id', 'id_nivel', 'id_grado']);
            if (! $curso) {
                return ['error' => true, 'message' => 'Curso no encontrado'];
            }

            $notas = Valoracion::where('id_curso', $idCurso)->where('id_periodo_academico', $idPeriodo)
                ->get(['id_estudiante', 'id_dimension', 'id_escala']);

            // Las del grado y, si una ya no aplica pero tiene notas, también esa.
            $dimensiones = Dimension::whereIn('id', $this->dimensionesDe($curso->id_grado, self::modoDelNivel($curso->id_nivel)['tipo_unidad'])->pluck('id')->merge($notas->pluck('id_dimension'))->unique())
                ->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'descripcion']);

            $estudiantes = self::estudiantesDelCurso($idCurso, $notas->pluck('id_estudiante')->unique())
                ->get(['id_user', 'nombre', 'apellido', 'foto_carnet'])
                ->map(fn ($u) => $u->only(['id_user', 'nombre', 'apellido', 'foto_carnet']) + [
                    'notas' => $notas->where('id_estudiante', $u->id_user)->pluck('id_escala', 'id_dimension'),
                ])->values();

            return ['error' => false, 'message' => 'Consulta de notas', 'data' => [
                // Toda la escala (también inactiva): una nota vieja puede usar un desempeño que ya se desactivó.
                'escala' => EscalaValoracion::orderBy('orden')->get(['id', 'nombre', 'abreviatura', 'descripcion', 'color']),
                'dimensiones' => $dimensiones,
                'estudiantes' => $estudiantes,
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

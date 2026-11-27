<?php

namespace App\Services\FotosCurso;

use App\Models\FotosCurso\FotoCurso;
use App\Models\FotosCurso\PublicacionFotosCurso;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\FileStorageService;
use App\Services\GestionAcademica\CargaAcademicaService;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use App\Services\Cloudinary\CloudinaryUploadsAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos del curso (portal para padres). Publica un docente del curso (carga académica activa o
 * director de grupo), sin aprobación; borra el autor o quien tenga la opción 174. Cada
 * publicación queda en el año escolar activo cuando se crea, y los acudientes ven solo las del
 * año activo del curso actual de su hijo. Las fotos son privadas (`fotos-curso/`): al cliente
 * llega la ruta y la abre por /api/public, que valida la sesión.
 */
class FotosCursoService extends Service
{
    public const CARPETA = 'fotos-curso';
    public const MAX_FOTOS = 20;
    /** Publicaciones por página en la vista del docente. */
    public const POR_PAGINA = 10;
    public const LIMITE_BUSQUEDA = 30;

    /** Lo que trae cada publicación al listarla. */
    private const RELACIONES = ['fotos', 'cursos:id,nombre', 'asignatura:id,nombre', 'docente:id_user,nombre,apellido'];

    /**
     * Transformaciones de Cloudinary por uso: miniatura cuadrada (listas, calendario y novedad),
     * media (carrusel, 232px al doble) y grande (visor). f_auto/q_auto: WebP/AVIF y peso justo.
     */
    private const TAMANOS = [
        'miniatura' => 'w_256,h_256,c_fill,f_auto,q_auto',
        'media' => 'w_512,c_limit,f_auto,q_auto',
        'grande' => 'w_1600,c_limit,f_auto,q_auto',
    ];

    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    public function __construct(
        private FileStorageService $archivos,
        private CargaAcademicaService $carga,
        private AnioEscolarServices $anios,
    ) {
    }

    /**
     * Cursos donde puede publicar: los de su carga y el que dirige. Con la opción 174, todos los
     * activos (para moderar).
     */
    public function cursos(int $idUsuario, bool $moderador): array
    {
        $propios = $this->carga->obtenerCursosDocente($idUsuario);
        if ($propios['error']) {
            return $propios;
        }
        $idsPropios = collect($propios['data'])->pluck('id')->map(fn ($id) => (int) $id)->flip();

        try {
            // `puede_publicar`: solo en los propios (el moderador modera, no publica en cursos ajenos).
            $cursos = ($moderador ? DB::table('curso')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']) : collect($propios['data']))
                ->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre, 'puede_publicar' => $idsPropios->has((int) $c->id)])
                ->values();

            return ['error' => false, 'message' => 'Cursos obtenidos correctamente', 'data' => $cursos];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al obtener los cursos');
        }
    }

    /** El docente dicta en el curso o lo dirige (carga activa). */
    public function esDocenteDelCurso(int $idDocente, int $idCurso): bool
    {
        $cursos = $this->carga->obtenerCursosDocente($idDocente);

        return ! $cursos['error'] && collect($cursos['data'])->contains(fn ($c) => (int) $c->id === $idCurso);
    }

    /**
     * Para publicar: cada asignatura que dicta el docente con los cursos donde la dicta (carga
     * académica activa), y al final «Actividad del curso» (sin asignatura) con todos sus cursos
     * (carga y el que dirige): salidas, actos, dirección de grupo.
     */
    public function opciones(int $idDocente): array
    {
        try {
            $filas = DB::table('academico_carga_academica as ca')
                ->join('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
                ->join('academico_asignatura as a', 'a.id', '=', 'da.id_asignatura')
                ->join('curso as c', 'c.id', '=', 'ca.id_curso')
                ->where(fn ($q) => $q->where('ca.id_docente', $idDocente)->orWhere('da.id_docente', $idDocente))
                ->where('ca.activo', 1)
                ->where('c.activo', 1)
                ->orderBy('a.nombre')
                ->orderBy('c.nombre')
                ->get(['a.id as id_asignatura', 'a.nombre as asignatura', 'c.id as id_curso', 'c.nombre as curso']);

            $grupos = $filas->groupBy('id_asignatura')->map(fn ($g) => [
                'id_asignatura' => (int) $g->first()->id_asignatura,
                'asignatura' => $g->first()->asignatura,
                'cursos' => $g->unique('id_curso')->map(fn ($f) => ['id' => (int) $f->id_curso, 'nombre' => $f->curso])->values(),
            ])->values();

            $propios = $this->carga->obtenerCursosDocente($idDocente);
            $todos = collect($propios['error'] ? [] : $propios['data'])->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values();
            if ($todos->isNotEmpty()) {
                $grupos->push(['id_asignatura' => null, 'asignatura' => null, 'cursos' => $todos]);
            }

            return ['error' => false, 'message' => 'Opciones obtenidas correctamente', 'data' => $grupos];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al obtener tus asignaturas y cursos');
        }
    }

    /** Todos los cursos están en las opciones del docente para esa asignatura (o sin asignatura). */
    public function puedePublicarEn(int $idDocente, ?int $idAsignatura, array $idCursos): bool
    {
        $opciones = $this->opciones($idDocente);
        if ($opciones['error'] || $idCursos === []) {
            return false;
        }

        $grupo = collect($opciones['data'])->first(fn ($g) => $g['id_asignatura'] === $idAsignatura);
        $permitidos = collect($grupo['cursos'] ?? [])->pluck('id');

        return collect($idCursos)->every(fn ($id) => $permitidos->contains((int) $id));
    }

    /**
     * Publicaciones del año activo de un curso, la más reciente primero (vista del docente), de a
     * POR_PAGINA: con muchas fotos al año, la página no las carga todas («Cargar más»).
     */
    public function listar(int $idCurso, int $idUsuario, bool $moderador, int $pagina = 1): array
    {
        try {
            $anio = $this->anioActivo();
            if (! $anio) {
                return ['error' => false, 'message' => 'No hay un año escolar activo', 'data' => ['publicaciones' => [], 'hay_mas' => false, 'total_fotos' => 0]];
            }

            // Una de más para saber si hay otra página sin contar todas.
            $filas = $this->consulta($idCurso, $anio->id)
                ->skip((max($pagina, 1) - 1) * self::POR_PAGINA)
                ->take(self::POR_PAGINA + 1)
                ->get();

            $data = $filas->take(self::POR_PAGINA)->map(fn (PublicacionFotosCurso $p) => [
                ...$this->paraVista($p),
                // El autor siempre; el moderador, cualquiera.
                'puede_borrar' => $moderador || $p->id_docente === $idUsuario,
            ])->values();

            return ['error' => false, 'message' => 'Publicaciones obtenidas correctamente', 'data' => [
                'publicaciones' => $data,
                'hay_mas' => $filas->count() > self::POR_PAGINA,
                'total_publicaciones' => PublicacionFotosCurso::where('id_anio_escolar', $anio->id)->whereIn('id', $this->delCurso($idCurso))->count(),
                'total_fotos' => $this->totalFotos($idCurso, $anio->id),
            ]];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al obtener las publicaciones');
        }
    }

    /**
     * Portal: las publicaciones de un mes ("YYYY-MM") del año activo. El portal pide solo los meses
     * que muestra (la semana y el carrusel), nunca el año entero. El que llama ya validó el vínculo.
     */
    public function delMes(int $idCurso, string $mes): Collection
    {
        $anio = $this->anioActivo();
        if (! $anio) {
            return collect();
        }

        $inicio = Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay();

        return $this->consulta($idCurso, $anio->id)
            ->whereBetween('fecha', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()])
            ->get()
            ->map(fn ($p) => $this->paraVista($p));
    }

    /**
     * Portal: búsqueda en el año activo por descripción, asignatura, docente o nombre del mes ("octubre"). La
     * colación de la BD ya ignora tildes y mayúsculas. Máximo LIMITE_BUSQUEDA publicaciones.
     */
    public function buscar(int $idCurso, string $texto): Collection
    {
        $anio = $this->anioActivo();
        $texto = trim($texto);
        if (! $anio || $texto === '') {
            return collect();
        }

        $like = '%' . addcslashes($texto, '%_\\') . '%';
        $mes = self::MESES[mb_strtolower($texto)] ?? null;

        return $this->consulta($idCurso, $anio->id)
            ->where(function ($q) use ($like, $mes) {
                $q->where('descripcion', 'like', $like)
                    ->orWhereHas('asignatura', fn ($a) => $a->where('nombre', 'like', $like))
                    ->orWhereHas('docente', fn ($d) => $d->whereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", [$like]));
                if ($mes) {
                    $q->orWhereMonth('fecha', $mes);
                }
            })
            ->take(self::LIMITE_BUSQUEDA)
            ->get()
            ->map(fn ($p) => $this->paraVista($p));
    }

    /**
     * Portal: resumen del año para el calendario, sin las fotos: por día, cuántas hay y la portada
     * (la primera foto de la publicación más reciente de ese día), más el total del año.
     */
    public function calendario(int $idCurso): array
    {
        $anio = $this->anioActivo();
        if (! $anio) {
            return ['dias' => [], 'total' => 0];
        }

        $dias = DB::table('fotos_curso_publicaciones as p')
            ->join('fotos_curso_publicacion_curso as pc', 'pc.id_publicacion', '=', 'p.id')
            ->join('fotos_curso_fotos as f', 'f.id_publicacion', '=', 'p.id')
            ->where('pc.id_curso', $idCurso)
            ->where('p.id_anio_escolar', $anio->id)
            ->groupBy('p.fecha')
            ->orderBy('p.fecha')
            ->selectRaw('p.fecha, COUNT(f.id) as total, MAX(p.id) as ultima')
            ->get();

        // La primera foto de la publicación más reciente de cada día.
        $portadas = FotoCurso::whereIn('id_publicacion', $dias->pluck('ultima'))
            ->orderBy('orden')->orderBy('id')
            ->get(['id_publicacion', 'ruta'])
            ->unique('id_publicacion')
            ->keyBy('id_publicacion');

        return [
            'dias' => $dias->map(fn ($d) => [
                'fecha' => (string) $d->fecha,
                'total' => (int) $d->total,
                'portada' => ($r = $portadas->get($d->ultima)?->ruta) ? ['ruta' => $r, 'urls' => $this->urls($r, ['miniatura'])] : null,
            ])->values(),
            'total' => (int) $dias->sum('total'),
        ];
    }

    /**
     * @param  UploadedFile[]  $fotos
     */
    public function publicar(array $data, array $fotos, int $idDocente): array
    {
        $anio = $this->anioActivo();
        if (! $anio) {
            return ['error' => true, 'message' => 'No hay un año escolar activo: no se puede publicar', 'data' => null];
        }

        $fecha = Carbon::parse($data['fecha'])->startOfDay();
        if ($fecha->gt(Carbon::today())) {
            return ['error' => true, 'message' => 'La fecha de la actividad no puede ser futura', 'data' => null];
        }
        if (! empty($anio->fecha_inicio) && $fecha->lt(Carbon::parse($anio->fecha_inicio))) {
            return ['error' => true, 'message' => 'La fecha es anterior al año escolar en curso', 'data' => null];
        }

        // Los archivos se suben antes de escribir: si falla la BD, se borran (abajo).
        $rutas = [];
        try {
            foreach ($fotos as $foto) {
                $rutas[] = $this->archivos->uploadFile($foto, self::CARPETA)['ruta'];
            }

            $publicacion = DB::transaction(function () use ($data, $anio, $idDocente, $fecha, $rutas) {
                $publicacion = PublicacionFotosCurso::create([
                    'id_anio_escolar' => $anio->id,
                    'id_docente' => $idDocente,
                    'id_asignatura' => $data['id_asignatura'] ?? null,
                    'fecha' => $fecha->toDateString(),
                    'descripcion' => $data['descripcion'] ?? null,
                    'confirma_autorizacion' => true,
                ]);

                // Una sola publicación (y una sola copia de las fotos) para todos sus cursos.
                $publicacion->cursos()->attach(array_values(array_unique(array_map('intval', $data['id_cursos']))));

                foreach ($rutas as $orden => $ruta) {
                    FotoCurso::create(['id_publicacion' => $publicacion->id, 'ruta' => $ruta, 'orden' => $orden]);
                }

                return $publicacion;
            });

            return [
                'error' => false,
                'message' => count($rutas) === 1 ? 'Foto publicada' : 'Fotos publicadas',
                'data' => [...$this->paraVista($publicacion->load(self::RELACIONES)), 'puede_borrar' => true],
            ];
        } catch (Exception $e) {
            $this->borrarArchivos($rutas);

            return $this->sendError($e, 'Error al publicar las fotos');
        }
    }

    public function eliminar(int $id, int $idUsuario, bool $moderador): array
    {
        try {
            $publicacion = PublicacionFotosCurso::with('fotos')->find($id);

            if (! $publicacion) {
                return ['error' => true, 'message' => 'La publicación no existe', 'data' => null];
            }
            if (! $moderador && $publicacion->id_docente !== $idUsuario) {
                return ['error' => true, 'message' => 'Solo quien la publicó puede borrarla', 'data' => null, 'status' => 403];
            }

            $rutas = $publicacion->fotos->pluck('ruta')->all();
            $publicacion->delete(); // las fotos caen por la FK en cascada
            $this->borrarArchivos($rutas);

            return ['error' => false, 'message' => 'Publicación borrada', 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al borrar la publicación');
        }
    }

    private function anioActivo(): ?object
    {
        return $this->anios->obtenerUltimoAnioEscolar()['data'] ?? null;
    }

    /** Publicaciones del año de un curso, la más reciente primero, con sus fotos, cursos, asignatura y docente. */
    private function consulta(int $idCurso, int $idAnio): Builder
    {
        return PublicacionFotosCurso::with(self::RELACIONES)
            ->whereIn('id', $this->delCurso($idCurso))
            ->where('id_anio_escolar', $idAnio)
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    /** Subconsulta: ids de las publicaciones que van a un curso. */
    private function delCurso(int $idCurso): \Closure
    {
        return fn ($q) => $q->select('id_publicacion')->from('fotos_curso_publicacion_curso')->where('id_curso', $idCurso);
    }

    private function totalFotos(int $idCurso, int $idAnio): int
    {
        return FotoCurso::whereHas('publicacion', fn ($q) => $q->whereIn('id', $this->delCurso($idCurso))->where('id_anio_escolar', $idAnio))->count();
    }

    private function paraVista(PublicacionFotosCurso $p): array
    {
        return [
            'id' => $p->id,
            'fecha' => $p->fecha->toDateString(),
            'descripcion' => $p->descripcion,
            // null: actividad del curso, sin asignatura.
            'asignatura' => $p->asignatura?->nombre,
            // Todos los cursos donde se ve (el padre sabe si fue con otro curso).
            'cursos' => $p->cursos->sortBy('nombre')->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values(),
            // Separados: el frontend los formatea con nombrePersona (mayúsculas, apellido ".").
            'docente' => $p->docente ? ['nombre' => $p->docente->nombre, 'apellido' => $p->docente->apellido] : null,
            'fotos' => $p->fotos->map(fn (FotoCurso $f) => ['id' => $f->id, 'ruta' => $f->ruta, 'urls' => $this->urls($f->ruta)])->values(),
        ];
    }

    /**
     * URLs firmadas del CDN para cada tamaño (TAMANOS), si el disco es Cloudinary: el navegador
     * las pide directo a Cloudinary y las guarda en caché, sin pasar por Laravel (con
     * /api/public/{ruta} cada miniatura era una petición a PHP con 302 y sin caché). Son fotos
     * (ArchivosPrivados::esFoto), que ya van firmadas en las listas. null en un disco local: el
     * frontend usa /api/public/{ruta}?w= (publicFileUrl).
     *
     * @param  string[]|null  $solo  tamaños a firmar (todos si es null)
     */
    private function urls(string $ruta, ?array $solo = null): ?array
    {
        $adapter = Storage::disk(config('filesystems.uploads_disk', 'public'))->getAdapter();
        if (! $adapter instanceof CloudinaryUploadsAdapter) {
            return null;
        }

        $urls = [];
        foreach ($solo ? array_intersect_key(self::TAMANOS, array_flip($solo)) : self::TAMANOS as $tamano => $transformacion) {
            $urls[$tamano] = $adapter->recurso($ruta, $transformacion)[2];
        }

        return $urls;
    }

    /** Fuera de la transacción: si el disco falla, solo se registra (la BD ya quedó bien). */
    private function borrarArchivos(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            try {
                $this->archivos->eliminar($ruta);
            } catch (Exception $e) {
                Log::warning('No se pudo borrar una foto del curso', ['ruta' => $ruta, 'error' => $e->getMessage()]);
            }
        }
    }
}

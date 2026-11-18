<?php

namespace App\Services\CursosAltura;

use App\Models\CursosAltura\CursoAltura;
use App\Models\CursosAltura\EntidadSeguridadSocial;
use App\Models\CursosAltura\SeguridadSocialTrabajador;
use App\Models\CursosAltura\TrabajoAltura;
use App\Models\Usuarios\Usuario;
use App\Services\Documentos\DocumentoPdfService;
use App\Services\FileStorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CursosAlturaServices
{
    private const CARPETA = 'gestion-humana/cursos-altura';

    // Privada en Cloudinary, como todo gestion-humana/ (ver ArchivosPrivados).
    private const CARPETA_SEGURIDAD_SOCIAL = 'gestion-humana/seguridad-social';

    /**
     * Nombre completo para buscar: hay nombres guardados con espacios de más ("Estefani ") y
     * apellidos en NULL, que con CONCAT(nombre, ' ', apellido) impedían encontrar "nombre apellido".
     */
    private const NOMBRE_COMPLETO = "CONCAT_WS(' ', TRIM(nombre), TRIM(apellido))";

    /** Datos propios de un trabajo en altura creado en Gestión Humana (en uno de reporte salen del reporte). */
    private const CAMPOS_TRABAJO_MANUAL = ['tipo_trabajo', 'id_bloque', 'id_area', 'elemento', 'descripcion'];

    /** Opcionales del permiso, para cualquier trabajo: si se llenan, salen impresos. */
    private const CAMPOS_TRABAJO_OPCIONALES = ['altura_aproximada', 'hora_inicio', 'fecha_finalizacion'];

    private const RELACIONES_SEGURIDAD_SOCIAL = [
        'seguridadSocial:id,id_user,id_eps,id_afp,id_arl,ruta_eps,ruta_afp,ruta_arl',
        'seguridadSocial.eps:id,nombre,activo',
        'seguridadSocial.afp:id,nombre,activo',
        'seguridadSocial.arl:id,nombre,activo',
    ];

    public function __construct(
        private FileStorageService $fileStorage,
        private DocumentoPdfService $documentos,
    ) {}

    /** @param  bool  $conSeguridadSocial  agrega `seguridad_social` (entidades por tipo, sin documentos) a cada fila */
    public function listar(array $filtros, int $perPage, bool $conSeguridadSocial = false): array
    {
        try {
            $query = CursoAltura::with('usuario:id_user,nombre,apellido,documento')
                ->when($conSeguridadSocial, fn ($q) => $q->with(self::RELACIONES_SEGURIDAD_SOCIAL))
                ->where('activo', 1)
                ->orderByDesc('fecha_expedicion')
                ->orderByDesc('id');

            if (! empty($filtros['s'])) {
                $s = preg_replace('/\s+/', ' ', trim($filtros['s']));
                $query->whereHas('usuario', fn ($q) => $q->where(DB::raw(self::NOMBRE_COMPLETO), 'like', "%{$s}%")
                    ->orWhere('documento', 'like', "%{$s}%"));
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($c) => $this->adjuntarDatos($c));

            return ['error' => false, 'message' => 'Cursos obtenidos correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Operativos a los que se les puede registrar un curso (select del formulario). */
    public function operativos(): array
    {
        try {
            $usuarios = CursoAltura::filtrarOperativos(Usuario::query())
                ->orderBy('nombre')
                ->get(['id_user', 'nombre', 'apellido', 'perfil']);

            return ['error' => false, 'message' => 'Operativos obtenidos correctamente', 'data' => $usuarios];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Operativos con curso vigente en $fecha, hoy por defecto (select de trabajadores en alturas). */
    public function operativosVigentes(?string $fecha = null): array
    {
        try {
            $usuarios = CursoAltura::filtrarOperativos(Usuario::query())
                ->whereIn('id_user', CursoAltura::vigentes($fecha)->select('id_user'))
                ->orderBy('nombre')
                ->get(['id_user', 'nombre', 'apellido']);

            return ['error' => false, 'message' => 'Operativos obtenidos correctamente', 'data' => $usuarios];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $data, int $idLog, UploadedFile $certificado): array
    {
        try {
            $subido = $this->fileStorage->uploadFile($certificado, self::CARPETA);

            $curso = CursoAltura::create([
                'id_user' => $data['id_user'],
                'ruta_certificado' => $subido['ruta'],
                'fecha_expedicion' => $data['fecha_expedicion'],
                'fecha_finalizacion' => $data['fecha_finalizacion'] ?? null,
                'user_log' => $idLog,
                'activo' => 1,
            ]);

            return ['error' => false, 'message' => 'Curso registrado correctamente', 'data' => $this->adjuntarDatos($curso->load('usuario:id_user,nombre,apellido,documento'))];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, int $idLog, ?UploadedFile $certificado): array
    {
        try {
            $curso = CursoAltura::where('activo', 1)->find($id);

            if (! $curso) {
                return ['error' => true, 'message' => 'Curso no encontrado', 'status' => 404];
            }

            $ruta = $certificado
                ? $this->fileStorage->reemplazar($certificado, $curso->ruta_certificado, self::CARPETA)['ruta']
                : $curso->ruta_certificado;

            $curso->update([
                'id_user' => $data['id_user'],
                'ruta_certificado' => $ruta,
                'fecha_expedicion' => $data['fecha_expedicion'],
                'fecha_finalizacion' => $data['fecha_finalizacion'] ?? null,
                'user_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Curso actualizado correctamente', 'data' => $this->adjuntarDatos($curso->fresh('usuario:id_user,nombre,apellido,documento'))];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Baja lógica (activo=0): el certificado se conserva como soporte histórico. */
    public function eliminar(int $id, int $idLog): array
    {
        try {
            $curso = CursoAltura::where('activo', 1)->find($id);

            if (! $curso) {
                return ['error' => true, 'message' => 'Curso no encontrado', 'status' => 404];
            }

            $curso->update(['activo' => 0, 'user_log' => $idLog]);

            return ['error' => false, 'message' => 'Curso eliminado correctamente', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Crea el trabajo en altura de un reporte/mantenimiento recién creado. Sin transacción
     * propia: la llama InventarioServices dentro de la suya. Los ids ya vienen validados.
     *
     * @param  array<int, int>  $idsOperativos
     */
    public function asignarTrabajo(int $idReporte, string $fecha, array $idsOperativos, int $idLog): void
    {
        if (empty($idsOperativos)) {
            return;
        }

        TrabajoAltura::create([
            'id_reporte' => $idReporte,
            'fecha_trabajo' => Carbon::parse($fecha)->toDateString(),
            'user_log' => $idLog,
        ])->operativos()->sync(array_map('intval', $idsOperativos));
    }

    /**
     * Trabajos en altura de los dos orígenes con sus datos en una sola forma: los de un
     * reporte toman tipo, ubicación, elemento y descripción del reporte; los creados en
     * Gestión Humana (`id_reporte` null), los propios. `solucionado`: en uno de reporte, si el
     * reporte tiene respuesta solucionada; en uno manual, si se marcó finalizado.
     */
    private function consultaTrabajos(): Builder
    {
        return TrabajoAltura::query()
            ->leftJoin('reportes as rp', 'rp.id', '=', 'trabajos_altura.id_reporte')
            ->leftJoin('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
            ->leftJoin('areas as ar', 'ar.id', '=', DB::raw('COALESCE(trabajos_altura.id_area, rp.id_area, iv.id_area)'))
            ->leftJoin('bloques as b', 'b.id', '=', DB::raw('COALESCE(trabajos_altura.id_bloque, iv.id_bloque, ar.id_bloque)'))
            ->with('operativos:id_user,nombre,apellido,documento')
            ->select([
                'trabajos_altura.id', 'trabajos_altura.id_reporte', 'trabajos_altura.fecha_trabajo',
                'trabajos_altura.id_bloque', 'trabajos_altura.id_area',
                'trabajos_altura.altura_aproximada', 'trabajos_altura.hora_inicio', 'trabajos_altura.fecha_finalizacion',
                'trabajos_altura.finalizado_en',
                'rp.tipo_reporte',
                DB::raw("CASE WHEN trabajos_altura.id_reporte IS NULL THEN 'manual' ELSE 'reporte' END AS origen"),
                DB::raw("COALESCE(trabajos_altura.tipo_trabajo, CASE WHEN rp.tipo_reporte = 2 THEN 'Mantenimiento preventivo' ELSE 'Mantenimiento correctivo' END) AS tipo_trabajo"),
                DB::raw("COALESCE(trabajos_altura.descripcion, NULLIF(TRIM(rp.descripcion), ''), rp.observacion) AS descripcion"),
                DB::raw('COALESCE(trabajos_altura.elemento, iv.descripcion) AS item'),
                'ar.nombre as area', 'b.nombre as bloque',
                DB::raw('CASE WHEN rp.id IS NULL THEN trabajos_altura.finalizado_en IS NOT NULL ELSE EXISTS(SELECT 1 FROM reportes s WHERE s.id_reporte = rp.id AND s.estado = 3) END AS solucionado'),
            ]);
    }

    /** Trabajos en altura (Gestión Humana > Trabajos en altura). */
    public function listarTrabajos(array $filtros, int $perPage): array
    {
        try {
            $query = $this->consultaTrabajos()
                ->orderByDesc('trabajos_altura.fecha_trabajo')
                ->orderByDesc('trabajos_altura.id');

            if (! empty($filtros['s'])) {
                $s = '%'.preg_replace('/\s+/', ' ', trim($filtros['s'])).'%';
                $query->where(fn ($q) => $q->where('iv.descripcion', 'like', $s)
                    ->orWhere('trabajos_altura.elemento', 'like', $s)
                    ->orWhere('trabajos_altura.descripcion', 'like', $s)
                    ->orWhere('ar.nombre', 'like', $s)
                    ->orWhere('b.nombre', 'like', $s)
                    ->orWhereHas('operativos', fn ($o) => $o->where(DB::raw(self::NOMBRE_COMPLETO), 'like', $s)));
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($t) => $t->solucionado = (bool) $t->solucionado);

            return ['error' => false, 'message' => 'Trabajos obtenidos correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Para los selects del trabajo en altura manual: los bloques activos con sus áreas activas y,
     * aparte, las áreas activas que no tienen bloque asignado (`otras_areas`), que se pueden
     * elegir con cualquier bloque.
     *
     * @return array{error: bool, message: string, data?: array{bloques: mixed, otras_areas: mixed}}
     */
    public function ubicaciones(): array
    {
        try {
            $areas = DB::table('areas')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre', 'id_bloque']);
            $porBloque = $areas->whereNotNull('id_bloque')->groupBy('id_bloque');
            $soloIdNombre = fn ($lista) => $lista->map(fn ($a) => ['id' => (int) $a->id, 'nombre' => $a->nombre])->values();

            $bloques = DB::table('bloques')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($b) => [
                    'id' => (int) $b->id,
                    'nombre' => $b->nombre,
                    'areas' => $soloIdNombre($porBloque->get($b->id, collect())),
                ]);

            return ['error' => false, 'message' => 'Ubicaciones obtenidas correctamente', 'data' => [
                'bloques' => $bloques,
                'otras_areas' => $soloIdNombre($areas->whereNull('id_bloque')),
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * El área, si viene, debe ser del bloque elegido o no tener bloque asignado ("Otras áreas").
     * Devuelve el mensaje de error o null.
     */
    private function errorUbicacion(?int $idBloque, ?int $idArea): ?string
    {
        if (! $idArea) {
            return null;
        }

        $bloqueDelArea = DB::table('areas')->where('id', $idArea)->value('id_bloque');

        return $bloqueDelArea === null || (int) $bloqueDelArea === (int) $idBloque ? null : 'El área seleccionada no pertenece al bloque';
    }

    /**
     * Crea un trabajo en altura sin reporte ni mantenimiento (Gestión Humana). `$datos` ya
     * validados: tipo_trabajo, id_bloque, id_area?, elemento, descripcion, fecha_trabajo y
     * los opcionales del permiso. Los operativos, con curso vigente en la fecha.
     *
     * @param  array<int, int>  $idsOperativos
     */
    public function crearTrabajo(array $datos, array $idsOperativos, int $idLog): array
    {
        try {
            if ($error = $this->errorUbicacion($datos['id_bloque'] ?? null, $datos['id_area'] ?? null)) {
                return ['error' => true, 'message' => $error, 'status' => 422];
            }

            $trabajo = DB::transaction(function () use ($datos, $idsOperativos, $idLog) {
                $trabajo = TrabajoAltura::create([
                    ...Arr::only($datos, [...self::CAMPOS_TRABAJO_MANUAL, ...self::CAMPOS_TRABAJO_OPCIONALES]),
                    'fecha_trabajo' => $datos['fecha_trabajo'],
                    'user_log' => $idLog,
                ]);
                $trabajo->operativos()->sync(array_map('intval', $idsOperativos));

                return $trabajo;
            });

            return ['error' => false, 'message' => 'Trabajo en altura creado correctamente', 'data' => ['id' => $trabajo->id]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Edita un trabajo en altura. Uno manual cambia todos sus datos y necesita al menos un
     * operativo (para quitarlo está eliminarTrabajo). Uno de reporte cambia solo la fecha,
     * los operativos y los opcionales del permiso; sin operativos, deja de ser en altura y se
     * borra. Solo se tocan los campos que vienen en `$datos`.
     *
     * @param  array<int, int>  $idsOperativos
     */
    public function actualizarTrabajo(int $id, array $datos, array $idsOperativos, int $idLog): array
    {
        try {
            $trabajo = TrabajoAltura::find($id);

            if (! $trabajo) {
                return ['error' => true, 'message' => 'Trabajo en altura no encontrado', 'status' => 404];
            }

            if (empty($idsOperativos)) {
                if ($trabajo->esManual()) {
                    return ['error' => true, 'message' => 'El trabajo necesita al menos un trabajador. Para quitarlo, elimínelo.', 'status' => 422];
                }

                $trabajo->delete();

                return ['error' => false, 'message' => 'El trabajo ya no requiere personal en alturas', 'data' => []];
            }

            $campos = ['fecha_trabajo', ...self::CAMPOS_TRABAJO_OPCIONALES, ...($trabajo->esManual() ? self::CAMPOS_TRABAJO_MANUAL : [])];
            $cambios = Arr::only($datos, $campos);

            if ($trabajo->esManual()) {
                $idBloque = array_key_exists('id_bloque', $cambios) ? $cambios['id_bloque'] : $trabajo->id_bloque;
                $idArea = array_key_exists('id_area', $cambios) ? $cambios['id_area'] : $trabajo->id_area;

                if ($error = $this->errorUbicacion($idBloque, $idArea)) {
                    return ['error' => true, 'message' => $error, 'status' => 422];
                }
            }

            DB::transaction(function () use ($trabajo, $cambios, $idsOperativos, $idLog) {
                $trabajo->update([...$cambios, 'user_log' => $idLog]);
                $trabajo->operativos()->sync(array_map('intval', $idsOperativos));
            });

            return ['error' => false, 'message' => 'Trabajo actualizado correctamente', 'data' => $trabajo->fresh('operativos:id_user,nombre,apellido,documento')];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Marca finalizado (cuándo y quién) o reabre un trabajo creado en Gestión Humana. Los de un
     * reporte toman el estado del reporte: se solucionan desde el reporte.
     */
    public function cambiarEstadoTrabajo(int $id, bool $finalizado, int $idUser): array
    {
        try {
            $trabajo = TrabajoAltura::find($id);

            if (! $trabajo) {
                return ['error' => true, 'message' => 'Trabajo en altura no encontrado', 'status' => 404];
            }

            if (! $trabajo->esManual()) {
                return ['error' => true, 'message' => 'El estado de este trabajo lo da su reporte o mantenimiento.', 'status' => 422];
            }

            $trabajo->update($finalizado
                ? ['finalizado_en' => now(), 'finalizado_por' => $idUser]
                : ['finalizado_en' => null, 'finalizado_por' => null]);

            return ['error' => false, 'message' => $finalizado ? 'Trabajo marcado como finalizado' : 'Trabajo reabierto', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Elimina un trabajo creado en Gestión Humana. Los de un reporte se quitan sin trabajadores. */
    public function eliminarTrabajo(int $id): array
    {
        try {
            $trabajo = TrabajoAltura::find($id);

            if (! $trabajo) {
                return ['error' => true, 'message' => 'Trabajo en altura no encontrado', 'status' => 404];
            }

            if (! $trabajo->esManual()) {
                return ['error' => true, 'message' => 'Este trabajo viene de un reporte o mantenimiento: para quitarlo, déjelo sin trabajadores.', 'status' => 422];
            }

            $trabajo->delete();

            return ['error' => false, 'message' => 'Trabajo en altura eliminado', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Permiso de alturas por el reporte/mantenimiento (Reportes de Áreas Comunes). */
    public function permisoAlturasPdf(int $idReporte): array
    {
        $idTrabajo = TrabajoAltura::where('id_reporte', $idReporte)->value('id');

        return $idTrabajo
            ? $this->permisoTrabajoPdf($idTrabajo)
            : ['error' => true, 'message' => 'El reporte no existe o no tiene trabajadores de alturas asignados', 'status' => 404];
    }

    /**
     * Permiso de Trabajo en Alturas (Res. 4272 de 2021) de un trabajo en altura, de reporte o
     * manual: todos sus operativos en un solo PDF (documento `permisoAlturas` de
     * src/documentos del frontend, formato SG-SST de Gestión Humana). Por trabajador: EPS,
     * AFP y ARL de `seguridad_social_trabajador` y la vigencia del curso que cubre la fecha.
     */
    public function permisoTrabajoPdf(int $idTrabajo): array
    {
        try {
            $trabajo = $this->consultaTrabajos()->where('trabajos_altura.id', $idTrabajo)->first();

            if (! $trabajo || $trabajo->operativos->isEmpty()) {
                return ['error' => true, 'message' => 'El trabajo no existe o no tiene trabajadores de alturas asignados', 'status' => 404];
            }

            $fecha = $trabajo->fecha_trabajo->toDateString();
            // Por operativo: el curso que cubre la fecha del trabajo; si no hay, el más reciente.
            $cursos = CursoAltura::where('activo', 1)
                ->whereIn('id_user', $trabajo->operativos->pluck('id_user'))
                ->orderByDesc('fecha_expedicion')
                ->get()
                ->groupBy('id_user');

            $seguridadSocial = SeguridadSocialTrabajador::with(['eps:id,nombre', 'afp:id,nombre', 'arl:id,nombre'])
                ->whereIn('id_user', $trabajo->operativos->pluck('id_user'))
                ->get()
                ->keyBy('id_user');

            $trabajadores = $trabajo->operativos->map(function ($op) use ($cursos, $fecha, $seguridadSocial) {
                $delOperativo = $cursos->get($op->id_user, collect());
                $curso = $delOperativo->first(fn ($c) => $c->fecha_expedicion->toDateString() <= $fecha
                    && ($c->fecha_finalizacion === null || $c->fecha_finalizacion->toDateString() >= $fecha)) ?? $delOperativo->first();
                $ss = $seguridadSocial->get($op->id_user);

                return [
                    'nombre' => trim("{$op->nombre} {$op->apellido}"),
                    'documento' => (string) ($op->documento ?? ''),
                    'eps' => $ss?->eps?->nombre ?? 'Sin registro',
                    'afp' => $ss?->afp?->nombre ?? 'Sin registro',
                    'arl' => $ss?->arl?->nombre ?? 'Sin registro',
                    'certificado' => match (true) {
                        ! $curso => 'Sin registro',
                        $curso->fecha_finalizacion === null => 'Sin vencimiento',
                        default => 'Vig. '.$curso->fecha_finalizacion->format('d/m/Y'),
                    },
                ];
            })->values()->all();

            $contenido = $this->documentos->generar('permisoAlturas', [
                'tipo' => $trabajo->tipo_trabajo ?? '',
                'fechaEjecucion' => $trabajo->fecha_trabajo->format('d/m/Y'),
                'bloque' => $trabajo->bloque ?? '',
                'area' => $trabajo->area ?: 'General',
                'elemento' => $trabajo->item ?? '',
                'descripcion' => $trabajo->descripcion ?? '',
                // Opcionales: si vienen se imprimen; si no, quedan las rayas para llenar en sitio.
                'alturaAproximada' => $trabajo->altura_aproximada !== null ? rtrim(rtrim((string) $trabajo->altura_aproximada, '0'), '.') : null,
                'horaInicio' => $trabajo->hora_inicio ? substr($trabajo->hora_inicio, 0, 5) : null,
                'fechaFinalizacion' => $trabajo->fecha_finalizacion?->format('d/m/Y'),
                'trabajadores' => $trabajadores,
            ], auth()->user()?->correo);

            $sufijo = $trabajo->id_reporte ?? "trabajo_{$trabajo->id}";

            return ['error' => false, 'message' => 'PDF generado', 'data' => [
                'contenido' => $contenido,
                'nombre_archivo' => "permiso_alturas_{$sufijo}.pdf",
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Catálogo activo de EPS, AFP y ARL. */
    public function entidades(): array
    {
        try {
            $entidades = EntidadSeguridadSocial::where('activo', 1)->orderBy('tipo')->orderBy('nombre')->get(['id', 'tipo', 'nombre']);

            return ['error' => false, 'message' => 'Entidades obtenidas correctamente', 'data' => $entidades];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Crea la entidad o, si ya existía desactivada con ese nombre, la reactiva. */
    public function crearEntidad(string $tipo, string $nombre, int $idLog): array
    {
        try {
            $entidad = EntidadSeguridadSocial::updateOrCreate(['tipo' => $tipo, 'nombre' => $nombre], ['user_log' => $idLog, 'activo' => 1]);

            return ['error' => false, 'message' => 'Entidad creada correctamente', 'data' => $entidad->only(['id', 'tipo', 'nombre'])];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarEntidad(int $id, string $nombre, int $idLog): array
    {
        try {
            $entidad = EntidadSeguridadSocial::where('activo', 1)->find($id);

            if (! $entidad) {
                return ['error' => true, 'message' => 'Entidad no encontrada', 'status' => 404];
            }

            $entidad->update(['nombre' => $nombre, 'user_log' => $idLog]);

            return ['error' => false, 'message' => 'Entidad actualizada correctamente', 'data' => $entidad->only(['id', 'tipo', 'nombre'])];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Baja lógica: deja de ofrecerse, pero los trabajadores que ya la tienen la conservan. */
    public function eliminarEntidad(int $id, int $idLog): array
    {
        try {
            $entidad = EntidadSeguridadSocial::where('activo', 1)->find($id);

            if (! $entidad) {
                return ['error' => true, 'message' => 'Entidad no encontrada', 'status' => 404];
            }

            $entidad->update(['activo' => 0, 'user_log' => $idLog]);

            return ['error' => false, 'message' => 'Entidad eliminada correctamente', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** EPS/AFP/ARL de un trabajador con sus `url_*`; data null si aún no tiene. */
    public function seguridadSocial(int $idUser): array
    {
        try {
            // Con las entidades: una desactivada sigue asignada y el formulario necesita su nombre.
            $registro = SeguridadSocialTrabajador::with(['eps:id,nombre,activo', 'afp:id,nombre,activo', 'arl:id,nombre,activo'])->where('id_user', $idUser)->first();

            return ['error' => false, 'message' => 'Seguridad social obtenida correctamente', 'data' => $registro ? $this->adjuntarUrls($registro) : null];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Guarda la EPS/AFP/ARL del trabajador (solo si tiene algún curso de altura activo, vigente
     * o no). Los ids ausentes quedan en null; un documento nuevo reemplaza al anterior y, sin
     * documento, se conserva el actual.
     *
     * @param  array<string, mixed>  $data  id_eps|id_afp|id_arl y documento_* (UploadedFile) ya validados
     */
    public function guardarSeguridadSocial(int $idUser, array $data, int $idLog): array
    {
        try {
            if (! CursoAltura::where('id_user', $idUser)->where('activo', 1)->exists()) {
                return ['error' => true, 'message' => 'El trabajador no tiene curso de altura registrado', 'status' => 422];
            }

            $registro = SeguridadSocialTrabajador::firstOrNew(['id_user' => $idUser]);

            foreach (['eps', 'afp', 'arl'] as $tipo) {
                $registro->{"id_{$tipo}"} = $data["id_{$tipo}"] ?? null;

                $documento = $data["documento_{$tipo}"] ?? null;
                if ($documento instanceof UploadedFile) {
                    $registro->{"ruta_{$tipo}"} = $this->fileStorage->reemplazar($documento, $registro->{"ruta_{$tipo}"}, self::CARPETA_SEGURIDAD_SOCIAL)['ruta'];
                }
            }

            $registro->user_log = $idLog;
            $registro->save();

            return ['error' => false, 'message' => 'Seguridad social guardada correctamente', 'data' => $this->adjuntarUrls($registro)];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function adjuntarUrls(SeguridadSocialTrabajador $registro): SeguridadSocialTrabajador
    {
        foreach (['eps', 'afp', 'arl'] as $tipo) {
            $registro->{"url_{$tipo}"} = $this->fileStorage->url($registro->{"ruta_{$tipo}"});
        }

        return $registro;
    }

    private function adjuntarDatos(CursoAltura $curso): CursoAltura
    {
        $curso->url_certificado = $this->fileStorage->url($curso->ruta_certificado);
        // Misma regla que CursoAltura::scopeVigentes, sin una consulta por fila.
        $hoy = Carbon::today();
        $curso->vigente = $curso->activo === 1
            && $curso->fecha_expedicion->lte($hoy)
            && ($curso->fecha_finalizacion === null || $curso->fecha_finalizacion->gte($hoy));

        return $curso;
    }
}

<?php

namespace App\Services\CursosAltura;

use App\Models\CursosAltura\CursoAltura;
use App\Models\CursosAltura\EntidadSeguridadSocial;
use App\Models\CursosAltura\SeguridadSocialTrabajador;
use App\Models\CursosAltura\TrabajoAltura;
use App\Models\Usuarios\Usuario;
use App\Pdf\Areas\PermisoAlturasPdfService;
use App\Services\FileStorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CursosAlturaServices
{
    private const CARPETA = 'gestion-humana/cursos-altura';

    // Privada en Cloudinary, como todo gestion-humana/ (ver ArchivosPrivados).
    private const CARPETA_SEGURIDAD_SOCIAL = 'gestion-humana/seguridad-social';

    private const RELACIONES_SEGURIDAD_SOCIAL = [
        'seguridadSocial:id,id_user,id_eps,id_afp,id_arl,ruta_eps,ruta_afp,ruta_arl',
        'seguridadSocial.eps:id,nombre,activo',
        'seguridadSocial.afp:id,nombre,activo',
        'seguridadSocial.arl:id,nombre,activo',
    ];

    public function __construct(
        private FileStorageService $fileStorage,
        private PermisoAlturasPdfService $permisoPdf,
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
                $s = $filtros['s'];
                $query->whereHas('usuario', fn ($q) => $q->where(DB::raw("CONCAT(nombre, ' ', apellido)"), 'like', "%{$s}%")
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

    /** Trabajos en altura (Gestión Humana > Trabajos en altura). */
    public function listarTrabajos(array $filtros, int $perPage): array
    {
        try {
            $query = TrabajoAltura::query()
                ->join('reportes as rp', 'rp.id', '=', 'trabajos_altura.id_reporte')
                ->join('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
                ->leftJoin('areas as ar', 'ar.id', '=', DB::raw('COALESCE(rp.id_area, iv.id_area)'))
                ->leftJoin('bloques as b', 'b.id', '=', DB::raw('COALESCE(iv.id_bloque, ar.id_bloque)'))
                ->with('operativos:id_user,nombre,apellido,documento')
                ->select([
                    'trabajos_altura.id', 'trabajos_altura.id_reporte', 'trabajos_altura.fecha_trabajo',
                    'rp.tipo_reporte',
                    DB::raw('COALESCE(NULLIF(TRIM(rp.descripcion), \'\'), rp.observacion) AS descripcion'),
                    'iv.descripcion as item', 'ar.nombre as area', 'b.nombre as bloque',
                    DB::raw('EXISTS(SELECT 1 FROM reportes s WHERE s.id_reporte = rp.id AND s.estado = 3) AS solucionado'),
                ])
                ->orderByDesc('trabajos_altura.fecha_trabajo')
                ->orderByDesc('trabajos_altura.id');

            if (! empty($filtros['s'])) {
                $s = "%{$filtros['s']}%";
                $query->where(fn ($q) => $q->where('iv.descripcion', 'like', $s)
                    ->orWhere('ar.nombre', 'like', $s)
                    ->orWhereHas('operativos', fn ($o) => $o->where(DB::raw("CONCAT(nombre, ' ', apellido)"), 'like', $s)));
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($t) => $t->solucionado = (bool) $t->solucionado);

            return ['error' => false, 'message' => 'Trabajos obtenidos correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cambia la fecha y los operativos de un trabajo en altura. Sin operativos, el trabajo
     * deja de ser en altura y se borra.
     *
     * @param  array<int, int>  $idsOperativos
     */
    public function actualizarTrabajo(int $id, string $fecha, array $idsOperativos, int $idLog): array
    {
        try {
            $trabajo = TrabajoAltura::find($id);

            if (! $trabajo) {
                return ['error' => true, 'message' => 'Trabajo en altura no encontrado', 'status' => 404];
            }

            if (empty($idsOperativos)) {
                $trabajo->delete();

                return ['error' => false, 'message' => 'El trabajo ya no requiere personal en alturas', 'data' => []];
            }

            DB::transaction(function () use ($trabajo, $fecha, $idsOperativos, $idLog) {
                $trabajo->update(['fecha_trabajo' => $fecha, 'user_log' => $idLog]);
                $trabajo->operativos()->sync(array_map('intval', $idsOperativos));
            });

            return ['error' => false, 'message' => 'Trabajo actualizado correctamente', 'data' => $trabajo->fresh('operativos:id_user,nombre,apellido,documento')];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Permiso de Trabajo en Alturas (Res. 4272 de 2021) de un reporte/mantenimiento con
     * trabajo en altura: todos sus operativos en un solo PDF.
     */
    public function permisoAlturasPdf(int $idReporte): array
    {
        try {
            $trabajo = TrabajoAltura::with('operativos:id_user,nombre,apellido,documento')->where('id_reporte', $idReporte)->first();

            $reporte = $trabajo ? DB::table('reportes as rp')
                ->join('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
                ->leftJoin('areas as ar', 'ar.id', '=', DB::raw('COALESCE(rp.id_area, iv.id_area)'))
                ->leftJoin('bloques as b', 'b.id', '=', DB::raw('COALESCE(iv.id_bloque, ar.id_bloque)'))
                ->where('rp.id', $idReporte)
                ->whereNull('rp.id_reporte')
                ->first([
                    'rp.id', 'rp.tipo_reporte',
                    DB::raw('COALESCE(NULLIF(TRIM(rp.descripcion), \'\'), rp.observacion) AS descripcion'),
                    'iv.descripcion as item', 'ar.nombre as area', 'b.nombre as bloque',
                ]) : null;

            if (! $reporte || $trabajo->operativos->isEmpty()) {
                return ['error' => true, 'message' => 'El reporte no existe o no tiene trabajadores de alturas asignados', 'status' => 404];
            }

            $fecha = $trabajo->fecha_trabajo->toDateString();
            // Por operativo: el curso que cubre la fecha del trabajo; si no hay, el más reciente.
            $cursos = CursoAltura::where('activo', 1)
                ->whereIn('id_user', $trabajo->operativos->pluck('id_user'))
                ->orderByDesc('fecha_expedicion')
                ->get()
                ->groupBy('id_user');

            $operativos = $trabajo->operativos->map(function ($op) use ($cursos, $fecha) {
                $delOperativo = $cursos->get($op->id_user, collect());
                $curso = $delOperativo->first(fn ($c) => $c->fecha_expedicion->toDateString() <= $fecha
                    && ($c->fecha_finalizacion === null || $c->fecha_finalizacion->toDateString() >= $fecha)) ?? $delOperativo->first();

                return [
                    'nombre' => trim("{$op->nombre} {$op->apellido}"),
                    'documento' => $op->documento ?? '',
                    'curso_expedicion' => $curso?->fecha_expedicion?->format('d/m/Y') ?? 'Sin registro',
                    'curso_vigencia' => $curso ? ($curso->fecha_finalizacion?->format('d/m/Y') ?? 'Sin vencimiento') : 'Sin registro',
                ];
            })->all();

            $contenido = $this->permisoPdf->generate([
                'consecutivo' => $reporte->id,
                'tipo' => $reporte->tipo_reporte == 2 ? 'Mantenimiento preventivo' : 'Mantenimiento correctivo',
                'fecha_emision' => Carbon::now()->format('d/m/Y'),
                'fecha_trabajo' => $trabajo->fecha_trabajo->format('d/m/Y'),
                'bloque' => $reporte->bloque ?? '',
                'area' => $reporte->area ?? '',
                'item' => $reporte->item ?? '',
                'descripcion' => $reporte->descripcion ?? '',
                'operativos' => $operativos,
            ]);

            return ['error' => false, 'message' => 'PDF generado', 'data' => [
                'contenido' => $contenido,
                'nombre_archivo' => "permiso_alturas_{$reporte->id}.pdf",
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

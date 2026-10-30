<?php

namespace App\Services\CursosAltura;

use App\Models\CursosAltura\CursoAltura;
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

    public function __construct(
        private FileStorageService $fileStorage,
        private PermisoAlturasPdfService $permisoPdf,
    ) {}

    public function listar(array $filtros, int $perPage): array
    {
        try {
            $query = CursoAltura::with('usuario:id_user,nombre,apellido,documento')
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

    /** Operativos con curso vigente hoy (select de "requiere personal en alturas"). */
    public function operativosVigentes(): array
    {
        try {
            $usuarios = CursoAltura::filtrarOperativos(Usuario::query())
                ->whereIn('id_user', CursoAltura::vigentes()->select('id_user'))
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
     * Permiso de Trabajo en Alturas (Res. 4272 de 2021) de un reporte/mantenimiento con
     * operativo asignado (reportes.id_operativo).
     */
    public function permisoAlturasPdf(int $idReporte): array
    {
        try {
            $reporte = DB::table('reportes as rp')
                ->join('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
                ->join('usuarios as op', 'op.id_user', '=', 'rp.id_operativo')
                ->leftJoin('areas as ar', 'ar.id', '=', DB::raw('COALESCE(rp.id_area, iv.id_area)'))
                ->leftJoin('bloques as b', 'b.id', '=', DB::raw('COALESCE(iv.id_bloque, ar.id_bloque)'))
                ->where('rp.id', $idReporte)
                ->whereNull('rp.id_reporte')
                ->first([
                    'rp.id', 'rp.tipo_reporte', 'rp.fechareg', 'rp.fecha_respuesta', 'rp.id_operativo',
                    DB::raw('COALESCE(NULLIF(TRIM(rp.descripcion), \'\'), rp.observacion) AS descripcion'),
                    'iv.descripcion as item', 'ar.nombre as area', 'b.nombre as bloque',
                    DB::raw("CONCAT(op.nombre, ' ', op.apellido) AS operativo"), 'op.documento',
                ]);

            if (! $reporte) {
                return ['error' => true, 'message' => 'El reporte no existe o no tiene operativo de alturas asignado', 'status' => 404];
            }

            // El curso que cubre la fecha del trabajo; si ya no hay uno, el más reciente.
            $curso = CursoAltura::where('activo', 1)
                ->where('id_user', $reporte->id_operativo)
                ->orderByDesc('fecha_expedicion')
                ->first();

            $fechaTrabajo = $reporte->tipo_reporte == 2 ? $reporte->fecha_respuesta : $reporte->fechareg;

            $contenido = $this->permisoPdf->generate([
                'consecutivo' => $reporte->id,
                'tipo' => $reporte->tipo_reporte == 2 ? 'Mantenimiento preventivo' : 'Mantenimiento correctivo',
                'fecha_emision' => Carbon::now()->format('d/m/Y'),
                'fecha_trabajo' => $fechaTrabajo ? Carbon::parse($fechaTrabajo)->format('d/m/Y') : '',
                'bloque' => $reporte->bloque ?? '',
                'area' => $reporte->area ?? '',
                'item' => $reporte->item ?? '',
                'descripcion' => $reporte->descripcion ?? '',
                'operativo' => $reporte->operativo,
                'documento' => $reporte->documento ?? '',
                'curso_expedicion' => $curso?->fecha_expedicion?->format('d/m/Y') ?? 'Sin registro',
                'curso_vigencia' => $curso ? ($curso->fecha_finalizacion?->format('d/m/Y') ?? 'Sin vencimiento') : 'Sin registro',
            ]);

            return ['error' => false, 'message' => 'PDF generado', 'data' => [
                'contenido' => $contenido,
                'nombre_archivo' => "permiso_alturas_{$reporte->id}.pdf",
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
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

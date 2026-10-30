<?php

namespace App\Services\inventario;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\Periodo;
use App\Models\Inventario\ProgramacionApro;
use App\Models\Inventario\ProgramacionMant;
use App\Services\FileStorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Programación de mantenimientos (`programacion_mant` + avances en `programacion_apro`) —
 * recreación de /mantenimientos/programacion del legacy. `anio` y `mes` se guardan como texto
 * ('2025/2026', 'Enero') para no romper los registros del legacy.
 */
class ProgramacionMantServices
{
    private const CARPETA = 'inventario/programacion-mant';

    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function __construct(
        private FileStorageService $fileStorage,
    ) {}

    /** Base compartida por listado, estadísticas y calendario: mismos filtros, mismas filas. */
    private function consulta(array $f): Builder
    {
        return DB::table('programacion_mant as pm')
            ->leftJoin('periodos as p', 'p.id', '=', 'pm.periodo')
            ->leftJoin('usuarios as u', 'u.id_user', '=', 'pm.usuario')
            ->when($f['id_anio'] ?? null, fn ($q, $v) => $q->where('pm.anio', $this->anioTexto((int) $v)))
            ->when($f['id_periodo'] ?? null, fn ($q, $v) => $q->where('pm.periodo', $v))
            ->when($f['mes'] ?? null, fn ($q, $v) => $q->where('pm.mes', $v))
            ->when(isset($f['estado']) && $f['estado'] !== '', fn ($q) => $q->where('pm.estado', $f['estado']))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('pm.tipo', $v))
            // Rango: programaciones que tocan [desde, hasta] en algún día (fecha_fin NULL = un día).
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(pm.fecha_fin, pm.fecha_registro) >= ?', [$v]))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where('pm.fecha_registro', '<=', $v))
            ->when($f['search'] ?? null, fn ($q, $v) => $q->where('pm.descripcion', 'like', "%{$v}%"));
    }

    private function select(Builder $q): Builder
    {
        return $q->select(
            'pm.id', 'pm.descripcion', 'pm.tipo', 'pm.anio', 'pm.mes', 'pm.fecha_registro', 'pm.fecha_fin',
            'pm.usuario', 'pm.periodo', 'pm.estado', 'pm.is_inventario',
            'p.numero as periodo_numero', 'p.id_anio',
            DB::raw("TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) as usuario_nombre"),
        );
    }

    public function listar(array $f, int $perPage): array
    {
        try {
            $paginator = $this->select($this->consulta($f))
                ->orderByDesc('pm.fecha_registro')
                ->orderByDesc('pm.id')
                ->paginate($perPage);

            return ['error' => false, 'message' => 'Programaciones obtenidas correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Sin paginar, para el calendario (el front pide solo el rango del mes visible). */
    public function calendario(array $f): array
    {
        try {
            $filas = $this->select($this->consulta($f))->orderBy('pm.fecha_registro')->get();

            return ['error' => false, 'message' => 'Calendario obtenido correctamente', 'data' => $filas];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cumplimiento = (Terminado + Completado parcial) / total, mismo criterio que el legacy.
     */
    public function estadisticas(array $f): array
    {
        try {
            $porEstado = $this->consulta($f)
                ->selectRaw('pm.estado, COUNT(*) as total')
                ->groupBy('pm.estado')
                ->pluck('total', 'estado');

            $porTipo = $this->consulta($f)
                ->selectRaw('pm.tipo, COUNT(*) as total')
                ->groupBy('pm.tipo')
                ->pluck('total', 'tipo');

            $porMes = $this->consulta($f)
                ->whereNotNull('pm.fecha_registro')
                // Alias `ym`, no `mes`: chocaría con la columna pm.mes en el GROUP BY.
                ->selectRaw("DATE_FORMAT(pm.fecha_registro, '%Y-%m') as ym, pm.estado, COUNT(*) as total")
                ->groupBy('ym', 'pm.estado')
                ->orderBy('ym')
                ->get();

            $total = (int) $porEstado->sum();
            $cumplidas = (int) ($porEstado[1] ?? 0) + (int) ($porEstado[2] ?? 0);

            return ['error' => false, 'message' => 'Estadísticas obtenidas correctamente', 'data' => [
                'total' => $total,
                'cumplimiento' => $total ? round($cumplidas * 100 / $total, 1) : 0,
                // (object): con llaves 0,1,... seguidas json_encode lo volvería un array.
                'por_estado' => (object) $porEstado->all(),
                'por_tipo' => (object) $porTipo->all(),
                'por_mes' => $porMes,
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $data, int $idLog): array
    {
        try {
            $programacion = ProgramacionMant::create($this->campos($data, $idLog) + [
                'estado' => $data['estado'] ?? 0,
                'is_inventario' => 0,
            ]);

            return ['error' => false, 'message' => 'Programación registrada correctamente', 'data' => $programacion];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, int $idLog): array
    {
        try {
            $programacion = ProgramacionMant::find($id);

            if (! $programacion) {
                return ['error' => true, 'message' => 'Programación no encontrada', 'status' => 404];
            }

            $programacion->update($this->campos($data, $idLog) + ['estado' => $data['estado'] ?? $programacion->estado]);

            return ['error' => false, 'message' => 'Programación actualizada correctamente', 'data' => $programacion];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Borra la programación con sus avances y los archivos de estos. */
    public function eliminar(int $id): array
    {
        try {
            $programacion = ProgramacionMant::with('avances')->find($id);

            if (! $programacion) {
                return ['error' => true, 'message' => 'Programación no encontrada', 'status' => 404];
            }

            $archivos = $programacion->avances->pluck('archivo')->filter()->all();

            DB::transaction(function () use ($programacion) {
                $programacion->avances()->delete();
                $programacion->delete();
            });

            // Fuera de la transacción: un archivo huérfano es preferible a perder el registro.
            foreach ($archivos as $archivo) {
                $this->fileStorage->eliminar($archivo);
            }

            return ['error' => false, 'message' => 'Programación eliminada correctamente', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function avances(int $id): array
    {
        try {
            $avances = DB::table('programacion_apro as pa')
                ->leftJoin('usuarios as u', 'u.id_user', '=', 'pa.usuario')
                ->where('pa.id_programacion', $id)
                ->orderByDesc('pa.id')
                ->get([
                    'pa.id', 'pa.fecha_registro', 'pa.observacion', 'pa.estado', 'pa.archivo',
                    DB::raw("TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) as usuario_nombre"),
                ])
                // Los archivos del legacy (solo nombre) se migraron al mismo disco: url() sirve para ambos.
                ->map(fn ($a) => tap($a, fn ($a) => $a->url_archivo = $this->fileStorage->url($a->archivo)));

            return ['error' => false, 'message' => 'Avances obtenidos correctamente', 'data' => $avances];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** "Aprobar" del legacy: registra el avance (con archivo opcional) y mueve el estado. */
    public function registrarAvance(int $id, array $data, int $idLog, ?UploadedFile $archivo): array
    {
        try {
            $programacion = ProgramacionMant::find($id);

            if (! $programacion) {
                return ['error' => true, 'message' => 'Programación no encontrada', 'status' => 404];
            }

            $ruta = $archivo ? $this->fileStorage->uploadFile($archivo, self::CARPETA)['ruta'] : null;

            try {
                DB::transaction(function () use ($programacion, $data, $idLog, $ruta) {
                    ProgramacionApro::create([
                        'id_programacion' => $programacion->id,
                        'id_compra_asociada' => 0,
                        'fecha_registro' => $data['fecha_registro'],
                        'observacion' => $data['observacion'] ?? null,
                        'usuario' => $idLog,
                        'estado' => $data['estado'],
                        'archivo' => $ruta,
                    ]);
                    $programacion->update(['estado' => $data['estado']]);
                });
            } catch (Exception $e) {
                $this->fileStorage->eliminar($ruta);
                throw $e;
            }

            return ['error' => false, 'message' => 'Avance registrado correctamente', 'data' => $programacion];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Programación que deja cada lote de mantenimientos preventivos generado desde
     * /inventario/mantenimiento (InventarioServices::programarMantenimientoPreventivo).
     */
    public function crearDesdeMantenimiento(string $descripcion, string $fechaInicio, string $fechaFin, int $idPeriodo, int $idLog, int $tipo, bool $terminado): ProgramacionMant
    {
        return ProgramacionMant::create($this->campos([
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'fecha_registro' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'periodo' => $idPeriodo,
        ], $idLog) + ['estado' => $terminado ? 1 : 0, 'is_inventario' => 1]);
    }

    /** anio/mes salen del periodo y de la fecha, para que nunca contradigan a estos. */
    private function campos(array $data, int $idLog): array
    {
        $fecha = Carbon::parse($data['fecha_registro']);
        $periodo = Periodo::find($data['periodo']);

        $fechaFin = ! empty($data['fecha_fin']) ? Carbon::parse($data['fecha_fin'])->toDateString() : null;

        return [
            'descripcion' => $data['descripcion'],
            'tipo' => $data['tipo'],
            'fecha_registro' => $fecha->toDateString(),
            // Un solo día se guarda como NULL, igual que lo del legacy.
            'fecha_fin' => $fechaFin !== $fecha->toDateString() ? $fechaFin : null,
            'mes' => self::MESES[$fecha->month - 1],
            'anio' => $periodo ? $this->anioTexto($periodo->id_anio) : null,
            'periodo' => $data['periodo'],
            'usuario' => (string) $idLog,
        ];
    }

    private function anioTexto(int $idAnio): ?string
    {
        $anio = Anio::find($idAnio);

        return $anio ? "{$anio->anio_inicio}/{$anio->anio_fin}" : null;
    }
}

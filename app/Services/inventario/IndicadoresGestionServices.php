<?php

namespace App\Services\inventario;

use App\Models\Inventario\IndicadorGestion;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de gestión de mantenimiento (SAMI legacy: ModeloInventario::*IndicadorGestion*).
 * Sistemas = categorías tipo 1, Operativos = tipo 2.
 */
class IndicadoresGestionServices
{
    private function base(int $tipoCategoria, ?int $idAnio, ?int $periodo, ?int $idCategoria)
    {
        return DB::table('indicadores_gestion as ig')
            ->join('categoria as c', 'c.id', '=', 'ig.categoria')
            ->leftJoin('anio_escolar as ae', 'ae.id', '=', 'ig.anio')
            ->where('c.tipo_categoria', $tipoCategoria)
            ->when($idAnio, fn ($q, $v) => $q->where('ig.anio', $v))
            ->when($periodo, fn ($q, $v) => $q->where('ig.periodo', $v))
            ->when($idCategoria, fn ($q, $v) => $q->where('ig.categoria', $v));
    }

    /** Meta de cumplimiento del legacy: ≥ 70 % cumple. */
    private const META = 70;

    /** Columnas ordenables desde el DataTable remoto (sort/dir). */
    private const ORDEN = [
        'anio' => 'ae.anio_inicio',
        'periodo' => 'ig.periodo',
        'nom_categoria' => 'c.nombre',
        'cantidad_mantenimientos' => 'ig.cantidad_mantenimientos',
        'cantidad_equipos' => 'ig.cantidad_equipos',
        'porcentaje_cumplimiento' => 'ig.porcentaje_cumplimiento',
        'fechareg' => 'ig.fechareg',
    ];

    /**
     * Histórico completo paginado en el servidor, con `resumen` (total, promedio, bajo la
     * meta) de TODOS los registros filtrados, no solo de la página.
     */
    public function listar(int $tipoCategoria, ?int $idAnio, ?int $periodo, ?int $idCategoria, ?string $s, ?string $sort, string $dir, int $perPage): array
    {
        try {
            $query = $this->base($tipoCategoria, $idAnio, $periodo, $idCategoria)
                ->leftJoin('usuarios as u', 'u.id_user', '=', 'ig.id_log')
                ->when($s, function ($q, $s) {
                    $like = '%'.$s.'%';
                    $q->where(fn ($w) => $w->where('c.nombre', 'like', $like)
                        ->orWhere('ig.gestion', 'like', $like)
                        ->orWhere('ig.analisis_gestion', 'like', $like)
                        ->orWhereRaw("CONCAT(u.nombre, ' ', u.apellido) LIKE ?", [$like])
                        ->orWhereRaw("CONCAT(ae.anio_inicio, '-', ae.anio_fin) LIKE ?", [$like]));
                });

            $resumen = (clone $query)
                ->selectRaw('COUNT(*) as total, ROUND(AVG(ig.porcentaje_cumplimiento), 1) as promedio, COALESCE(SUM(ig.porcentaje_cumplimiento < ?), 0) as bajo_meta', [self::META])
                ->first();

            $data = $query
                ->select(
                    'ig.id',
                    'ig.categoria as id_categoria',
                    'c.nombre as nom_categoria',
                    'ig.departamento',
                    'ig.periodo',
                    'ig.cantidad_mantenimientos',
                    'ig.cantidad_equipos',
                    'ig.porcentaje_cumplimiento',
                    'ig.gestion',
                    'ig.analisis_gestion',
                    'ig.fechareg',
                    DB::raw("CONCAT(ae.anio_inicio, '-', ae.anio_fin) as anio"),
                    DB::raw("TRIM(CONCAT(u.nombre, ' ', u.apellido)) as responsable")
                )
                ->orderBy(self::ORDEN[$sort] ?? 'ig.fechareg', isset(self::ORDEN[$sort]) ? $dir : 'desc')
                ->orderByDesc('ig.id')
                ->paginate($perPage);

            return [
                'error' => false,
                'message' => 'Indicadores de gestión obtenidos',
                'data' => $data,
                'resumen' => [
                    'total' => (int) $resumen->total,
                    'promedio' => $resumen->promedio !== null ? (float) $resumen->promedio : null,
                    'bajo_meta' => (int) $resumen->bajo_meta,
                ],
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    public function crear(array $d, ?int $idLog): array
    {
        try {
            $existe = IndicadorGestion::where('anio', $d['anio'])
                ->where('categoria', $d['categoria'])
                ->where('periodo', $d['periodo'])
                ->exists();

            if ($existe) {
                return ['error' => true, 'status' => 422, 'message' => 'Ya existe un indicador con el mismo año, categoría y periodo', 'data' => null];
            }

            $tipo = DB::table('categoria')->where('id', $d['categoria'])->value('tipo_categoria');

            // El % se calcula acá, nunca se confía en el que mande el cliente.
            $indicador = IndicadorGestion::create([
                'categoria' => $d['categoria'],
                'anio' => $d['anio'],
                'periodo' => $d['periodo'],
                'departamento' => $tipo == 1 ? 'sistemas' : 'operativos',
                'cantidad_mantenimientos' => $d['cantidad_mantenimientos'],
                'cantidad_equipos' => $d['cantidad_equipos'],
                'porcentaje_cumplimiento' => $d['cantidad_equipos'] > 0
                    ? round($d['cantidad_mantenimientos'] / $d['cantidad_equipos'] * 100, 2)
                    : 0,
                'gestion' => $d['gestion'] ?? null,
                'analisis_gestion' => $d['analisis'] ?? null,
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Indicador de gestión generado', 'data' => $indicador];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    public function actualizarAnalisis(int $id, ?string $analisis): array
    {
        try {
            $indicador = IndicadorGestion::find($id);

            if (! $indicador) {
                return ['error' => true, 'status' => 404, 'message' => 'El indicador no existe', 'data' => null];
            }

            $indicador->update(['analisis_gestion' => $analisis]);

            return ['error' => false, 'message' => 'Análisis actualizado', 'data' => $indicador];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    /** Comportamiento del % por categoría y año/periodo; sin $idAnio = consulta extendida. */
    public function grafica(int $tipoCategoria, ?int $idAnio): array
    {
        try {
            $data = $this->base($tipoCategoria, $idAnio, null, null)
                ->select(
                    'ig.porcentaje_cumplimiento as porcentaje',
                    'c.nombre as categoria',
                    DB::raw("CONCAT(ae.anio_inicio, '-', ae.anio_fin, ' (', ig.periodo, ' periodo)') as anio_periodo")
                )
                ->orderBy('ae.anio_inicio')
                ->orderBy('ig.periodo')
                ->get();

            return ['error' => false, 'message' => 'Gráfica de indicadores obtenida', 'data' => $data];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'data' => null];
        }
    }
}

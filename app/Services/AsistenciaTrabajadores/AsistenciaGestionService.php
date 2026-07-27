<?php

namespace App\Services\AsistenciaTrabajadores;

use App\Models\AsistenciaGestion\AsistenciaGestion;
use App\Models\Usuarios\Usuario;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class AsistenciaGestionService extends Service
{
    // Perfiles que NO se guardan en asistencia_gestion (excluidos del registro automático y del cálculo de faltantes)
    public const PERFILES_EXCLUIDOS_ASISTENCIA = [16, 1, 17, 28, 6];

    public function registrarAsistencia(int $idUsuario, string $fecha, string $hora): array
    {
        try {
            $existe = AsistenciaGestion::where('id_user', $idUsuario)
                ->where('fecha_asistencia', $fecha)
                ->exists();

            if ($existe) {
                return [
                    'error' => false,
                    'message' => 'Ya existe un registro de asistencia para este usuario en esta fecha',
                    'data' => null,
                ];
            }

            $asistencia = AsistenciaGestion::create([
                'id_user' => $idUsuario,
                'fecha_asistencia' => $fecha,
                'hora_asistencia' => $hora,
                'fechareg' => now(),
            ]);

            return [
                'error' => false,
                'message' => 'Asistencia registrada correctamente',
                'data' => $asistencia->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al registrar asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar asistencia',
                'data' => null,
            ];
        }
    }

    public function obtenerAsistencia(array $filtros): array
    {
        try {
            $query = AsistenciaGestion::with('usuario');

            if (!empty($filtros['id_usuario'])) {
                $query->porUsuario($filtros['id_usuario']);
            }

            if (!empty($filtros['id_perfil'])) {
                $query->porPerfil($filtros['id_perfil']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->fechaDesde($filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->fechaHasta($filtros['fecha_hasta']);
            }

            if (!empty($filtros['fecha'])) {
                $query->delDia($filtros['fecha']);
            }

            if (!empty($filtros['hora_desde'])) {
                $query->where('hora_asistencia', '>=', $filtros['hora_desde']);
            }

            if (!empty($filtros['hora_hasta'])) {
                $query->where('hora_asistencia', '<=', $filtros['hora_hasta']);
            }

            $perPage = $filtros['per_page'] ?? 50;
            $resultados = $query->orderBy('fecha_asistencia', 'desc')
                ->orderBy('hora_asistencia', 'desc')
                ->paginate($perPage);

            $data = $resultados->toArray();

            // "Faltó" solo tiene sentido para un día puntual: sin una fecha exacta no
            // hay un único universo de usuarios esperados contra el cual comparar.
            if (!empty($filtros['fecha'])) {
                $data['faltantes'] = $this->obtenerFaltantesDelDia(
                    $filtros['fecha'],
                    $filtros['id_perfil'] ?? null,
                    $filtros['id_usuario'] ?? null
                );
            }

            return [
                'error' => false,
                'message' => 'Asistencia obtenida correctamente',
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener asistencia',
                'data' => null,
            ];
        }
    }

    /**
     * Usuarios activos, no excluidos de asistencia_gestion, que no tienen ninguna
     * llegada registrada en la fecha dada. Representan las "faltas" del día: no
     * existe una fila en asistencia_gestion para ellos (a diferencia de una llegada,
     * una falta no deja registro propio, se infiere por ausencia).
     */
    private function obtenerFaltantesDelDia(string $fecha, ?int $idPerfil, ?int $idUsuario): array
    {
        $idsConAsistencia = AsistenciaGestion::whereDate('fecha_asistencia', $fecha)->pluck('id_user');

        return Usuario::where('estado', 'activo')
            ->whereNotIn('perfil', self::PERFILES_EXCLUIDOS_ASISTENCIA)
            ->whereNotIn('id_user', $idsConAsistencia)
            ->when($idPerfil, fn ($q) => $q->where('perfil', $idPerfil))
            ->when($idUsuario, fn ($q) => $q->where('id_user', $idUsuario))
            ->get(['id_user', 'nombre', 'perfil'])
            ->map(fn ($usuario) => [
                'id' => null,
                'id_user' => $usuario->id_user,
                'fecha_asistencia' => $fecha,
                'hora_asistencia' => null,
                'fechareg' => null,
                'puntualidad' => null,
                'estado' => 'faltó',
                'usuario' => $usuario,
            ])
            ->all();
    }

    public function obtenerResumenPorUsuario(array $filtros): array
    {
        try {
            $query = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select(
                    'ag.id_user',
                    'u.nombre',
                    'u.perfil',
                    DB::raw('COUNT(*) as total_asistencias'),
                    DB::raw('MIN(ag.hora_asistencia) as primera_asistencia'),
                    DB::raw('MAX(ag.hora_asistencia) as ultima_asistencia'),
                    DB::raw('AVG(TIME_TO_SEC(ag.hora_asistencia)) as promedio_segundos')
                )
                ->groupBy('ag.id_user', 'u.nombre', 'u.perfil');

            if (!empty($filtros['id_usuario'])) {
                $query->where('ag.id_user', $filtros['id_usuario']);
            }

            if (!empty($filtros['id_perfil'])) {
                $query->where('u.perfil', $filtros['id_perfil']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->where('ag.fecha_asistencia', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->where('ag.fecha_asistencia', '<=', $filtros['fecha_hasta']);
            }

            $resultados = $query->orderByDesc('total_asistencias')->get();

            $data = $resultados->map(fn ($r) => [
                'id_user' => $r->id_user,
                'nombre' => $r->nombre,
                'perfil' => $r->perfil,
                'total_asistencias' => (int) $r->total_asistencias,
                'primera_asistencia' => $r->primera_asistencia,
                'ultima_asistencia' => $r->ultima_asistencia,
                'promedio_hora' => $this->segundosATiempo((int) round($r->promedio_segundos)),
            ]);

            return [
                'error' => false,
                'message' => 'Resumen obtenido correctamente',
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener resumen de asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener resumen',
                'data' => null,
            ];
        }
    }

    public function obtenerDatosGrafica(array $filtros): array
    {
        try {
            $rangoFechas = $this->obtenerRangoFechas($filtros);

            $porDia = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select(
                    'ag.fecha_asistencia',
                    DB::raw('COUNT(DISTINCT ag.id_user) as usuarios_unicos'),
                    DB::raw('COUNT(*) as total_marcaciones')
                )
                ->whereBetween('ag.fecha_asistencia', [$rangoFechas['desde'], $rangoFechas['hasta']]);

            if (!empty($filtros['id_perfil'])) {
                $porDia->where('u.perfil', $filtros['id_perfil']);
            }

            $porDia = $porDia->groupBy('ag.fecha_asistencia')
                ->orderBy('ag.fecha_asistencia')
                ->get();

            $porPerfil = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select('u.perfil', DB::raw('COUNT(DISTINCT ag.id_user) as total_usuarios'))
                ->whereBetween('ag.fecha_asistencia', [$rangoFechas['desde'], $rangoFechas['hasta']])
                ->groupBy('u.perfil')
                ->get();

            $porHora = DB::table('asistencia_gestion')
                ->select(
                    DB::raw('HOUR(hora_asistencia) as hora'),
                    DB::raw('COUNT(*) as total')
                )
                ->whereBetween('fecha_asistencia', [$rangoFechas['desde'], $rangoFechas['hasta']])
                ->groupBy(DB::raw('HOUR(hora_asistencia)'))
                ->orderBy('hora')
                ->get();

            return [
                'error' => false,
                'message' => 'Datos de gráfica obtenidos correctamente',
                'data' => [
                    'rango' => $rangoFechas,
                    'por_dia' => $porDia,
                    'por_perfil' => $porPerfil,
                    'por_hora' => $porHora,
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener datos de gráfica');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener datos de gráfica',
                'data' => null,
            ];
        }
    }

    public function eliminarAsistencia(array $ids): array
    {
        try {
            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'No se proporcionaron IDs para eliminar',
                    'data' => null,
                ];
            }

            $eliminados = AsistenciaGestion::whereIn('id', $ids)->delete();

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} registros de asistencia",
                'data' => ['eliminados' => $eliminados],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar asistencia',
                'data' => null,
            ];
        }
    }

    private function segundosATiempo(int $segundos): string
    {
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv(($segundos % 3600), 60);
        return sprintf('%02d:%02d:00', $horas, $minutos);
    }

    private function obtenerRangoFechas(array $filtros): array
    {
        $hasta = $filtros['fecha_hasta'] ?? now()->toDateString();
        $desde = $filtros['fecha_desde'] ?? now()->subDays(30)->toDateString();

        return ['desde' => $desde, 'hasta' => $hasta];
    }
}

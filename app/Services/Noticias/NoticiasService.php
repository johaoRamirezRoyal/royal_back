<?php

namespace App\Services\Noticias;

use App\Models\Noticias\MensajeGeneral;
use App\Models\Noticias\MensajeProgramado;

class NoticiasService
{
    /**
     * El "mensaje general" es una única configuración editable (como el banner
     * informativo) — no una lista. Se opera siempre sobre la fila más reciente
     * (`asistencia_mensaje_general` trae datos legacy desde 2022, con varias filas de
     * prueba; la última es la vigente).
     */
    public function obtenerMensajeGeneral(): array
    {
        try {
            $mensaje = MensajeGeneral::orderByDesc('id')->first();

            return ['error' => false, 'data' => $mensaje];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarMensajeGeneral(array $datos, int $idLog): array
    {
        try {
            $mensaje = MensajeGeneral::orderByDesc('id')->first();

            $payload = [
                'titulo' => $datos['titulo'] ?? null,
                'imagen' => $datos['imagen'] ?? null,
                'mensaje' => $datos['mensaje'] ?? null,
                'activo' => $datos['activo'] ?? true,
                'id_log' => $idLog,
            ];

            if ($mensaje) {
                $mensaje->update($payload);
            } else {
                $mensaje = MensajeGeneral::create($payload + ['fechareg' => now()]);
            }

            return ['error' => false, 'data' => $mensaje];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarProgramados(array $filtros, int $perPage): array
    {
        try {
            $query = MensajeProgramado::with('nivelRelacion')
                ->orderByDesc('fecha')
                ->orderByDesc('id');

            if (isset($filtros['nivel']) && $filtros['nivel'] !== null && $filtros['nivel'] !== '') {
                $query->where('nivel', (int) $filtros['nivel']);
            }

            if (isset($filtros['activo']) && $filtros['activo'] !== null && $filtros['activo'] !== '') {
                $query->where('activo', (int) $filtros['activo']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
            }

            if (!empty($filtros['s'])) {
                $search = $filtros['s'];
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'like', "%{$search}%")
                        ->orWhere('mensaje', 'like', "%{$search}%");
                });
            }

            $paginator = $query->paginate($perPage);

            return ['error' => false, 'message' => 'Noticias obtenidas correctamente', 'data' => $paginator];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearProgramado(array $datos, int $idLog): array
    {
        try {
            $programado = MensajeProgramado::create([
                'fecha' => $datos['fecha'],
                'titulo' => $datos['titulo'],
                'imagen' => $datos['imagen'] ?? null,
                'mensaje' => $datos['mensaje'] ?? null,
                'url' => $datos['url'] ?? null,
                'nivel' => $datos['nivel'] ?? 0,
                'activo' => $datos['activo'] ?? true,
                'id_log' => $idLog,
                'fechareg' => now(),
            ]);

            return ['error' => false, 'data' => $programado];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarProgramado(int $id, array $datos): array
    {
        try {
            $programado = MensajeProgramado::find($id);

            if (!$programado) {
                return ['error' => true, 'message' => 'Noticia programada no encontrada'];
            }

            $programado->update([
                'fecha' => $datos['fecha'],
                'titulo' => $datos['titulo'],
                'imagen' => $datos['imagen'] ?? null,
                'mensaje' => $datos['mensaje'] ?? null,
                'url' => $datos['url'] ?? null,
                'nivel' => $datos['nivel'] ?? 0,
                'activo' => $datos['activo'] ?? true,
            ]);

            return ['error' => false, 'data' => $programado];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function cambiarEstadoProgramados(array $ids, int $estado): array
    {
        try {
            MensajeProgramado::whereIn('id', $ids)->update(['activo' => $estado]);

            return ['error' => false, 'message' => 'Noticias actualizadas correctamente'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

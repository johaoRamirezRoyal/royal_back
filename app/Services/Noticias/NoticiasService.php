<?php

namespace App\Services\Noticias;

use App\Mail\NoticiaMail;
use App\Models\Noticias\MensajeGeneral;
use App\Models\Noticias\MensajeProgramado;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class NoticiasService
{
    public function __construct(private MailService $mailService)
    {
    }

    /**
     * Cuántos días se muestra una noticia (contados desde su `fecha`) cuando no define su
     * propia `dias_visualizacion` — ver la columna en la migración
     * add_dias_visualizacion_to_asistencia_mensaje_table. Sin esto (o sin la columna),
     * una fila legacy de 2022 activa quedaría mostrándose para siempre, y `fecha` no
     * tiene columna de expiración propia.
     */
    private const DIAS_VISUALIZACION_DEFECTO = 10;

    /**
     * Mensaje general activo + programadas activas (tipo 'normal' y 'cumpleanos por
     * separado) que todavía están dentro de su ventana de visualización (`fecha` +
     * `dias_visualizacion`, o el default de arriba si no la definieron), visibles para
     * `$idNivel` (0 = "todos los niveles" del usuario ve todo; cualquier otro valor solo
     * ve lo propio de su nivel + lo de nivel 0). Pensado para el contenedor de noticias
     * del Home — a diferencia de `listarProgramados` (admin, paginado, sin filtro de
     * fecha/audiencia/tipo), esto es de solo lectura para cualquier usuario.
     */
    public function obtenerParaMostrar(?int $idNivel): array
    {
        try {
            $general = MensajeGeneral::orderByDesc('id')->first();

            return [
                'error' => false,
                'data' => [
                    'general' => $general && $general->activo ? $general : null,
                    'programadas' => $this->programadasVisiblesDeTipo('normal', $idNivel),
                    'cumpleanos' => $this->programadasVisiblesDeTipo('cumpleanos', $idNivel),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function programadasVisiblesDeTipo(string $tipo, ?int $idNivel)
    {
        $hoy = now()->toDateString();

        return MensajeProgramado::with('nivelRelacion')
            ->where('activo', 1)
            ->where('tipo', $tipo)
            ->whereDate('fecha', '<=', $hoy)
            // fecha + COALESCE(dias_visualizacion, default) >= hoy — el default no se
            // guarda como literal en cada fila, así que si DIAS_VISUALIZACION_DEFECTO
            // cambia más adelante, aplica también retroactivamente a lo que ya se dejó
            // en blanco.
            ->whereRaw(
                'DATE_ADD(fecha, INTERVAL COALESCE(dias_visualizacion, ?) DAY) >= ?',
                [self::DIAS_VISUALIZACION_DEFECTO, $hoy],
            )
            ->where(function ($query) use ($idNivel) {
                $query->where('nivel', 0);
                if ($idNivel) {
                    $query->orWhere('nivel', $idNivel);
                }
            })
            ->orderByDesc('fecha')
            ->get();
    }

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
                'tipo' => $datos['tipo'] ?? 'normal',
                'dias_visualizacion' => $datos['dias_visualizacion'] ?? null,
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
                'tipo' => $datos['tipo'] ?? $programado->tipo,
                'dias_visualizacion' => array_key_exists('dias_visualizacion', $datos) ? $datos['dias_visualizacion'] : $programado->dias_visualizacion,
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

    /**
     * Envía por correo el mensaje general (si está activo y no se envió hoy) y todas
     * las noticias programadas cuya `fecha` es hoy (si están activas y no se enviaron
     * antes) — pensado para correr una vez al día desde un comando programado (ver
     * EnviarNoticiasDiariasCommand). Nunca lanza: cada envío individual ya maneja sus
     * propios errores (MailService::send), así que un correo inválido o un fallo de SMTP
     * puntual no debe tumbar el resto del lote.
     */
    public function enviarPendientesDelDia(): array
    {
        $generalEnviado = $this->enviarMensajeGeneralSiCorresponde();
        $programadasEnviadas = $this->enviarProgramadosDelDia();

        return [
            'error' => false,
            'message' => sprintf(
                'Mensaje general: %s. Noticias programadas enviadas: %d.',
                $generalEnviado ? 'enviado' : 'sin cambios',
                $programadasEnviadas,
            ),
            'data' => ['general_enviado' => $generalEnviado, 'programadas_enviadas' => $programadasEnviadas],
        ];
    }

    private function enviarMensajeGeneralSiCorresponde(): bool
    {
        $mensaje = MensajeGeneral::orderByDesc('id')->first();

        if (!$mensaje || !$mensaje->activo) {
            return false;
        }

        $hoy = now()->toDateString();

        // Ya se envió hoy — el mensaje general no tiene `fecha` propia (es una sola fila
        // siempre vigente), así que sin este chequeo se reenviaría cada vez que corra el
        // comando, no una vez al día.
        if ($mensaje->ultimo_envio_fecha && $mensaje->ultimo_envio_fecha->toDateString() === $hoy) {
            return false;
        }

        $correos = $this->correosPorNivel(0);

        if (empty($correos)) {
            Log::warning('Noticias: mensaje general activo sin destinatarios que enviar.');
            return false;
        }

        $mailable = new NoticiaMail($mensaje->titulo ?: 'Noticia', $mensaje->mensaje, null, $mensaje->imagen);
        $enviados = 0;

        foreach ($correos as $correo) {
            if ($this->mailService->send($correo, $mailable)) {
                $enviados++;
            }
        }

        $mensaje->update(['ultimo_envio_fecha' => $hoy]);

        Log::info("Noticias: mensaje general enviado a {$enviados}/".count($correos).' destinatarios.');

        return true;
    }

    private function enviarProgramadosDelDia(): int
    {
        $pendientes = MensajeProgramado::where('activo', 1)
            ->where('tipo', 'cumpleanos')
            ->whereDate('fecha', now()->toDateString())
            ->whereNull('enviado_at')
            ->get();

        $enviadas = 0;

        foreach ($pendientes as $programado) {
            $correos = $this->correosPorNivel((int) ($programado->nivel ?? 0));

            if (empty($correos)) {
                Log::warning("Noticias: programada #{$programado->id} sin destinatarios para nivel {$programado->nivel}.");
                // Se marca igual como enviada — sin destinatarios que probar mañana
                // tampoco los va a tener, y su `fecha` ya pasó/está pasando hoy.
                $programado->update(['enviado_at' => now()]);
                continue;
            }

            $mailable = new NoticiaMail($programado->titulo, $programado->mensaje, $programado->url, $programado->imagen);
            $enviados = 0;

            foreach ($correos as $correo) {
                if ($this->mailService->send($correo, $mailable)) {
                    $enviados++;
                }
            }

            $programado->update(['enviado_at' => now()]);
            $enviadas++;

            Log::info("Noticias: programada #{$programado->id} ('{$programado->titulo}') enviada a {$enviados}/".count($correos).' destinatarios.');
        }

        return $enviadas;
    }

    /** 0 = todos los niveles activos. Cualquier otro valor filtra por ese `id_nivel`. */
    private function correosPorNivel(int $nivel): array
    {
        $query = Usuario::where('estado', 'activo')->whereNotNull('correo');

        if ($nivel !== 0) {
            $query->where('id_nivel', $nivel);
        }

        return $query->pluck('correo')->filter()->unique()->values()->all();
    }
}

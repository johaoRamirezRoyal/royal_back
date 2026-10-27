<?php

namespace App\Services\Noticias;

use App\Exceptions\MailRateLimitException;
use App\Mail\NoticiaMail;
use App\Models\CorreoInstitucional;
use App\Models\Noticias\ConfiguracionNoticias;
use App\Models\Noticias\MensajeGeneral;
use App\Models\Noticias\MensajeProgramado;
use App\Models\Noticias\Revista;
use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use Illuminate\Support\Carbon;
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
     * Grupos de `correos_institucionales` que administra esta pantalla (ver
     * resolverDestinatariosDistribucion) — etiqueta legible por grupo. Toda consulta de
     * esta sección filtra explícitamente por estas llaves para no tocar/exponer los
     * demás grupos de la tabla (ADMISIONES, BIBLIOTECA, ...), que son de otros módulos.
     */
    private const GRUPOS_DISTRIBUCION = [
        'NOTICIAS_TODOS' => 'Todos (trabajadores)',
        'NOTICIAS_PREESCOLAR' => 'Preescolar',
        'NOTICIAS_PRIMARIA' => 'Primaria',
        'NOTICIAS_SECUNDARIA' => 'Secundaria / Bachillerato',
        'NOTICIAS_ADMINISTRATIVO' => 'Administrativo',
    ];

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
                    // Todas las activas, más reciente primero — el Home muestra la primera
                    // y deja elegir otra si hay más de una.
                    'revistas' => Revista::where('activo', true)->latest('id')->get(),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarRevistas(): array
    {
        try {
            return ['error' => false, 'data' => Revista::orderByDesc('id')->get()];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearRevista(string $titulo, ?string $descripcion, string $url, string $publicId, int $idLog): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Revista publicada correctamente',
                'data' => Revista::create([
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'url' => $url,
                    'public_id' => $publicId,
                    'id_log' => $idLog,
                ]),
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarRevista(int $id, string $titulo, ?string $descripcion): array
    {
        $revista = Revista::find($id);
        if (!$revista) {
            return ['error' => true, 'message' => 'La revista no existe', 'status' => 404];
        }

        $revista->update(['titulo' => $titulo, 'descripcion' => $descripcion]);

        return ['error' => false, 'message' => 'Revista actualizada', 'data' => $revista];
    }

    public function cambiarEstadoRevista(int $id, bool $activo): array
    {
        $revista = Revista::find($id);
        if (!$revista) {
            return ['error' => true, 'message' => 'La revista no existe', 'status' => 404];
        }

        $revista->update(['activo' => $activo]);

        return ['error' => false, 'message' => 'Estado actualizado', 'data' => $revista];
    }

    /** Borra la fila y devuelve el public_id para que el controller elimine el PDF de Cloudinary. */
    public function eliminarRevista(int $id): array
    {
        $revista = Revista::find($id);
        if (!$revista) {
            return ['error' => true, 'message' => 'La revista no existe', 'status' => 404];
        }

        $revista->delete();

        return ['error' => false, 'message' => 'Revista eliminada', 'public_id' => $revista->public_id];
    }

    private function programadasVisiblesDeTipo(string $tipo, ?int $idNivel)
    {
        $hoy = now()->toDateString();
        // Un cumpleaños sin ventana propia se muestra solo el día del cumpleaños (0 días
        // después de `fecha`); antes tomaba los 10 de las noticias y seguía en el Home
        // hasta 10 días después. Las felicitaciones generadas no guardan
        // dias_visualizacion, así que esto también corrige las que ya existen.
        $diasDefecto = $tipo === 'cumpleanos' ? 0 : self::DIAS_VISUALIZACION_DEFECTO;

        $query = MensajeProgramado::with('nivelRelacion')
            ->where('activo', 1)
            ->where('tipo', $tipo)
            ->where(function ($query) use ($idNivel) {
                $query->where('nivel', 0);
                if ($idNivel) {
                    $query->orWhere('nivel', $idNivel);
                }
            });

        return $query
            ->whereDate('fecha', '<=', $hoy)
            // fecha + COALESCE(dias_visualizacion, default) >= hoy — el default no se
            // guarda como literal en cada fila, así que si DIAS_VISUALIZACION_DEFECTO
            // cambia más adelante, aplica también retroactivamente a lo que ya se dejó
            // en blanco.
            ->whereRaw(
                'DATE_ADD(fecha, INTERVAL COALESCE(dias_visualizacion, ?) DAY) >= ?',
                [$diasDefecto, $hoy],
            )
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * El "mensaje general" es una única configuración editable (como el banner
     * informativo) — no una lista. Se opera siempre sobre la fila más reciente
     * (`asistencia_mensaje_general` trae datos legacy desde 2022, con varias filas de
     * prueba; la última es la vigente).
     */
    public function obtenerConfiguracion(): array
    {
        try {
            return ['error' => false, 'data' => ConfiguracionNoticias::actual()];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarConfiguracion(bool $enviarCorreosProgramadas): array
    {
        try {
            $config = ConfiguracionNoticias::actual();
            $config->update(['enviar_correos_programadas' => $enviarCorreosProgramadas]);

            return [
                'error' => false,
                'message' => $enviarCorreosProgramadas
                    ? 'Envío de correos activado'
                    : 'Envío de correos desactivado',
                'data' => $config,
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

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
                'nivel' => $datos['nivel'],
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
                // Solo noticias normales: los cumpleaños los genera CumpleanosService.
                'tipo' => 'normal',
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
                // El tipo no se cambia al editar.
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
     * Envía por correo el mensaje general (si está activo y no se envió hoy) y los
     * cumpleaños programados de hoy (una sola vez, en su fecha exacta — ver
     * enviarProgramadosDelDia).
     * Corre cada hora desde un comando programado (ver EnviarNoticiasDiariasCommand).
     * Nunca lanza: cada envío individual ya maneja sus
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

        // Debe elegirse explícitamente un nivel (o "Todos", nivel=0) al editar el mensaje
        // — ver NoticiasController::actualizarGeneral. Sin nivel asignado (fila legacy
        // sin editar todavía) no se envía nada.
        if ($mensaje->nivel === null) {
            Log::warning('Noticias: mensaje general activo sin nivel configurado, no se envía.');
            return false;
        }

        $mailable = new NoticiaMail($mensaje->titulo ?: 'Noticia', $mensaje->mensaje, null, $mensaje->imagen);
        $enviados = 0;
        $total = 1;

        $distribucion = $this->resolverDestinatariosDistribucion((int) $mensaje->nivel);

        if (!empty($distribucion)) {
            $enviados = $this->mailService->send($distribucion, $mailable) ? count($distribucion) : 0;
        } else {
            $correos = $this->correosPorNivel((int) $mensaje->nivel);
            $total = count($correos);

            if (empty($correos)) {
                Log::warning('Noticias: mensaje general activo sin destinatarios que enviar.');
                return false;
            }

            foreach ($correos as $correo) {
                try {
                    if ($this->mailService->send($correo, $mailable)) {
                        $enviados++;
                    }
                } catch (MailRateLimitException $e) {
                    Log::warning("Noticias: mensaje general cortado por límite de envío del proveedor tras {$enviados}/{$total} destinatarios. {$e->getMessage()}");
                    break;
                }
            }
        }

        $mensaje->update(['ultimo_envio_fecha' => $hoy]);

        Log::info("Noticias: mensaje general enviado a {$enviados}/{$total} destinatarios" . (!empty($distribucion) ? ' (lista de distribución: ' . implode(', ', $distribucion) . ').' : '.'));

        return true;
    }

    private function enviarProgramadosDelDia(): int
    {
        // Interruptor de la pestaña "Noticias programadas": apagado, no se envía nada por
        // correo, pero tampoco se marca `enviado_at` — si se vuelve a encender el mismo
        // día, el envío de la hora siguiente manda los de hoy.
        if (!ConfiguracionNoticias::actual()->enviar_correos_programadas) {
            Log::info('Noticias: envío de correos de noticias programadas desactivado, no se envía nada.');
            return 0;
        }

        // Cada cumpleaños se envía una sola vez, en su fecha exacta. Las felicitaciones las
        // genera CumpleanosService una por persona y año (la repetición anual manual se retiró).
        $pendientes = MensajeProgramado::where('activo', 1)
            ->where('tipo', 'cumpleanos')
            ->whereDate('fecha', now()->toDateString())
            ->whereNull('enviado_at')
            ->get();

        $enviadas = 0;

        foreach ($pendientes as $programado) {
            // Sin destinatarios (total 0) no cuenta como enviada en el resumen del comando.
            if ($this->enviarProgramado($programado)['total'] > 0) {
                $enviadas++;
            }
        }

        return $enviadas;
    }

    /**
     * Envía una noticia programada a su lista (o a los correos de su nivel) y guarda el
     * resultado: `enviado_at` siempre, y `envio_fallido`/`envio_detalle` si el correo no
     * llegó a todos. Lo usan el envío de cada hora y "Reenviar correo" de Cumpleaños.
     * MailRateLimitException se propaga desde la lista de distribución (como antes: sin
     * marcar, la siguiente hora lo reintenta); con correos sueltos corta y queda fallido.
     *
     * @return array{enviados: int, total: int}
     */
    public function enviarProgramado(MensajeProgramado $programado): array
    {
        $mailable = new NoticiaMail($programado->titulo, $programado->mensaje, $programado->url, $programado->imagen);
        $nivel = (int) ($programado->nivel ?? 0);
        $distribucion = $this->resolverDestinatariosDistribucion($nivel);
        $enviados = 0;
        $total = 1;

        if (!empty($distribucion)) {
            $enviados = $this->mailService->send($distribucion, $mailable) ? 1 : 0;
        } else {
            $correos = $this->correosPorNivel($nivel);
            $total = count($correos);

            if (empty($correos)) {
                // Sin destinatarios, reintentar cada hora no cambia nada: queda marcada y fallida.
                Log::warning("Noticias: programada #{$programado->id} sin destinatarios para nivel {$programado->nivel}.");
            }

            foreach ($correos as $correo) {
                try {
                    if ($this->mailService->send($correo, $mailable)) {
                        $enviados++;
                    }
                } catch (MailRateLimitException $e) {
                    Log::warning("Noticias: programada #{$programado->id} cortada por límite de envío del proveedor tras {$enviados}/{$total} destinatarios. {$e->getMessage()}");
                    break;
                }
            }
        }

        $fallido = $total === 0 || $enviados < $total;
        $programado->update([
            'enviado_at' => now(),
            'envio_fallido' => $fallido,
            'envio_detalle' => match (true) {
                !$fallido => null,
                $total === 0 => 'No hay destinatarios para su nivel',
                !empty($distribucion) => 'No se pudo enviar a la lista de distribución',
                default => "Se envió a {$enviados} de {$total} destinatarios",
            },
        ]);

        Log::info("Noticias: programada #{$programado->id} ('{$programado->titulo}') enviada a {$enviados}/{$total}" . (!empty($distribucion) ? ' (lista de distribución: ' . implode(', ', $distribucion) . ').' : ' destinatarios.'));

        return ['enviados' => $enviados, 'total' => $total];
    }

    /**
     * Listado + catálogo de grupos + niveles (con el grupo que tengan asociado, si
     * alguno) para la pantalla de administración de correos de distribución.
     */
    public function listarCorreosDistribucion(): array
    {
        try {
            $correos = CorreoInstitucional::whereIn('grupo', array_keys(self::GRUPOS_DISTRIBUCION))
                ->orderBy('grupo')
                ->orderBy('id')
                ->get();

            $niveles = Nivel::orderBy('nombre')
                ->get(['id', 'nombre', 'grupo_correo_distribucion']);

            return [
                'error' => false,
                'data' => [
                    'grupos' => self::GRUPOS_DISTRIBUCION,
                    'correos' => $correos,
                    'niveles' => $niveles,
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Asocia (o desasocia, con `$grupo = null`) un nivel a uno de los grupos de
     * distribución de Noticias — lo que antes era un match hardcodeado por `nombre` (ver
     * migración add_grupo_correo_distribucion_to_nivel_table) ahora se edita desde la UI.
     */
    public function asignarGrupoDelNivel(int $idNivel, ?string $grupo): array
    {
        try {
            if ($grupo !== null && !array_key_exists($grupo, self::GRUPOS_DISTRIBUCION)) {
                return ['error' => true, 'message' => 'Grupo inválido'];
            }

            $nivel = Nivel::find($idNivel);

            if (!$nivel) {
                return ['error' => true, 'message' => 'Nivel no encontrado'];
            }

            $nivel->update(['grupo_correo_distribucion' => $grupo]);

            return ['error' => false, 'data' => $nivel];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearCorreoDistribucion(array $datos): array
    {
        try {
            if (!array_key_exists($datos['grupo'] ?? null, self::GRUPOS_DISTRIBUCION)) {
                return ['error' => true, 'message' => 'Grupo inválido'];
            }

            $correo = CorreoInstitucional::create([
                'grupo' => $datos['grupo'],
                'nombre' => $datos['nombre'] ?? null,
                'correo' => $datos['correo'],
                'activo' => $datos['activo'] ?? true,
            ]);

            return ['error' => false, 'data' => $correo];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarCorreoDistribucion(int $id, array $datos): array
    {
        try {
            // Scoped a los grupos de Noticias: no permite editar por esta vía filas de
            // otros módulos (ADMISIONES, BIBLIOTECA, ...) aunque adivinen el id.
            $correo = CorreoInstitucional::whereIn('grupo', array_keys(self::GRUPOS_DISTRIBUCION))->find($id);

            if (!$correo) {
                return ['error' => true, 'message' => 'Correo no encontrado'];
            }

            $correo->update([
                'nombre' => $datos['nombre'] ?? null,
                'correo' => $datos['correo'],
                'activo' => $datos['activo'] ?? true,
            ]);

            return ['error' => false, 'data' => $correo];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarCorreoDistribucion(int $id): array
    {
        try {
            $borrado = CorreoInstitucional::whereIn('grupo', array_keys(self::GRUPOS_DISTRIBUCION))
                ->where('id', $id)
                ->delete();

            if (!$borrado) {
                return ['error' => true, 'message' => 'Correo no encontrado'];
            }

            return ['error' => false, 'message' => 'Correo eliminado'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Destinatarios activos de `correos_institucionales` para el grupo asociado al nivel
     * dado (columna `nivel.grupo_correo_distribucion`, editable desde "Correos de
     * distribución") — un solo Mail::send con estos como "to" reparte el mensaje sin
     * repetir el incidente de rate-limit de mandar un correo por usuario. Vacío = ese
     * nivel no tiene grupo asociado (o el grupo no tiene filas activas), y el llamador
     * cae al envío individual de siempre vía `correosPorNivel`. nivel=0 ("Todos") no es
     * una fila real de `nivel`, así que usa siempre NOTICIAS_TODOS directo.
     */
    private function resolverDestinatariosDistribucion(int $nivel): array
    {
        $grupo = $nivel === 0 ? 'NOTICIAS_TODOS' : Nivel::find($nivel)?->grupo_correo_distribucion;

        if (!$grupo || !array_key_exists($grupo, self::GRUPOS_DISTRIBUCION)) {
            return [];
        }

        return CorreoInstitucional::where('grupo', $grupo)
            ->where('activo', true)
            ->pluck('correo')
            ->filter()
            ->unique()
            ->values()
            ->all();
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

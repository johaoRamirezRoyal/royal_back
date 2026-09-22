<?php

namespace App\Services\AsistenciaTrabajadores;

use App\Models\AsistenciaGestion\AsistenciaGestion;
use App\Models\AsistenciaGestion\ConfiguracionAsistencia;
use App\Models\LlegadasTarde\ConfiguracionLlegadasTarde;
use App\Models\Usuarios\Usuario;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use App\Services\MailService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsistenciaGestionService extends Service
{
    public function __construct(
        private MailService $mailService,
    ) {}

    // Mismo patrón que AdmisionesServices/LlegadasTarde: correo fijo del encargado de RH
    // que se notifica cuando el sistema cierra una salida automáticamente.
    private array $mailTo = ['hernando.ramirez@royalschool.edu.co'];

    // Perfiles que NO se guardan en asistencia_gestion (excluidos del registro automático y del cálculo de faltantes)
    public const PERFILES_EXCLUIDOS_ASISTENCIA = [16, 17, 28, 6];

    // Fila única de configuración global (ver migración create_configuracion_asistencia_table).
    private const ID_CONFIG = 1;

    // Perfiles fijos del aviso de llegada tarde (ver notificarLlegadaTarde) — Recursos
    // Humanos (todo el colegio) y Coordinador (acotado al nivel del trabajador, vía
    // Usuario::correosPorPerfilesYNivel).
    private const PERFIL_RECURSOS_HUMANOS = 8;
    private const PERFIL_COORDINADOR = 26;

    // Tope de destinatarios del aviso de llegada tarde: un solo correo a un grupo enorme
    // dispararía el límite del proveedor (ver el incidente de rate-limit de Noticias) — por
    // encima de esto se omite ese correo y se registra en logs.
    private const MAX_DESTINATARIOS_LLEGADA_TARDE = 30;

    // Nombres de los Person Group de Hikvision (mismos groupId que hikvisionattendanceService::GROUP_ID_POR_PERFIL).
    private const GRUPO_LABEL_POR_GROUP_ID = [
        2 => 'Admin. Dept.',
        8 => 'Estudiantes',
        9 => 'Profesores',
        10 => 'Trabajadores',
    ];

    /**
     * Nombre del departamento/grupo Hikvision al que pertenece un perfil, reutilizando la
     * misma asignación perfil→groupId que ya usa la integración con el dispositivo (no
     * se duplica esa lógica, solo la traducción final a texto legible).
     */
    private function grupoLabel(int $perfil): string
    {
        $groupId = hikvisionattendanceService::GROUP_ID_POR_PERFIL[$perfil] ?? null;

        return self::GRUPO_LABEL_POR_GROUP_ID[$groupId] ?? 'Sin departamento';
    }

    public function registrarAsistencia(int $idUsuario, string $fecha, string $hora): array
    {
        try {
            // firstOrCreate() intenta el create() y, si choca con el índice único
            // (asistencia_gestion_user_fecha_unique), reconsulta en vez de fallar:
            // así dos pushes casi simultáneos del mismo usuario (p. ej. un
            // reintento del dispositivo) no pueden duplicar la fila.
            $asistencia = AsistenciaGestion::firstOrCreate(
                ['id_user' => $idUsuario, 'fecha_asistencia' => $fecha],
                ['hora_asistencia' => $hora, 'fechareg' => now()]
            );

            if (!$asistencia->wasRecentlyCreated) {
                return [
                    'error' => false,
                    'message' => 'Ya existe un registro de asistencia para este usuario en esta fecha',
                    'data' => null,
                ];
            }

            $this->notificarLlegadaTardeDespuesDeResponder($asistencia);

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

    /**
     * Registro manual de una asistencia completa (entrada y, opcional, salida) — para corregir
     * un olvido del dispositivo. Queda marcada con QR = 2 ("Registro manual") y con una
     * observación que dice quién la registró. Respeta el índice único usuario+fecha: si ya hay
     * una asistencia ese día no la pisa (hay que editarla o eliminarla). El correo de llegada
     * tarde solo se envía si la fecha es hoy — un registro retroactivo no debe disparar avisos.
     */
    public function registrarAsistenciaManual(array $datos, string $registradoPor): array
    {
        try {
            $usuario = Usuario::select('id_user', 'perfil')->find($datos['id_user']);

            if (!$usuario) {
                return ['error' => true, 'message' => 'El usuario no existe.', 'data' => null];
            }

            if (in_array((int) $usuario->perfil, self::PERFILES_EXCLUIDOS_ASISTENCIA, true)) {
                return ['error' => true, 'message' => 'Este perfil no registra asistencia.', 'data' => null];
            }

            $conSegundos = fn (?string $hora) => $hora === null ? null : (strlen($hora) === 5 ? $hora . ':00' : $hora);
            $nota = "Registro manual por {$registradoPor}";
            $observacion = !empty($datos['observacion']) ? "{$nota}: {$datos['observacion']}" : $nota;

            $asistencia = AsistenciaGestion::firstOrCreate(
                ['id_user' => $datos['id_user'], 'fecha_asistencia' => $datos['fecha_asistencia']],
                [
                    'hora_asistencia' => $conSegundos($datos['hora_asistencia']),
                    'hora_salida' => $conSegundos($datos['hora_salida'] ?? null),
                    'observacion' => $observacion,
                    'QR' => 2,
                    'fechareg' => now(),
                ]
            );

            if (!$asistencia->wasRecentlyCreated) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una asistencia de este trabajador en esa fecha. Edítala o elimínala antes de registrarla de nuevo.',
                    'data' => null,
                ];
            }

            if ($asistencia->fecha_asistencia->isToday()) {
                $this->notificarLlegadaTardeDespuesDeResponder($asistencia);
            }

            return [
                'error' => false,
                'message' => 'Asistencia registrada manualmente',
                'data' => $asistencia->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al registrar asistencia manual');
            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar la asistencia manual',
                'data' => null,
            ];
        }
    }

    /**
     * Registra la marcación del día (entrada o salida) a partir de un evento de
     * marcación biométrica genérico (el dispositivo ya no distingue entrada/salida por
     * sí mismo — cada evento es solo "este usuario se marcó a esta hora"). El tipo se
     * resuelve con un typecheck sobre el estado del día, no sobre lo que reporte el
     * dispositivo:
     *   - Sin entrada registrada hoy -> esta marcación es la entrada.
     *   - Con entrada y sin salida -> esta marcación es la salida.
     *   - Con entrada y salida ya registradas -> se descarta (tercer evento del día).
     */
    /**
     * Hora mínima para marcar salida: el campo hora_minima_salida del horario aplicable al
     * usuario (mismo AsistenciaGestion::horarioAplicable() que ya usan la puntualidad y el
     * cierre automático — no se hardcodea un corte global, cada grupo tiene el suyo). Si el
     * usuario no existe o ningún horario aplica a su grupo/día, cae al valor global de
     * configuracion_asistencia (editable desde Configuración de asistencia).
     */
    private function horaMinimaSalidaParaUsuario(int $idUsuario): string
    {
        $usuario = Usuario::find($idUsuario);
        $grupoId = $usuario ? (hikvisionattendanceService::GROUP_ID_POR_PERFIL[(int) $usuario->perfil] ?? null) : null;
        $horario = AsistenciaGestion::horarioAplicable($grupoId);

        return $horario->hora_minima_salida
            ?? ConfiguracionAsistencia::find(self::ID_CONFIG)?->hora_minima_salida_defecto
            ?? '09:00:00';
    }

    public function registrarMarcacion(int $idUsuario, string $fecha, string $hora): array
    {
        try {
            $asistencia = AsistenciaGestion::where('id_user', $idUsuario)
                ->where('fecha_asistencia', $fecha)
                ->first();

            $tieneEntrada = $asistencia !== null;
            $tieneSalida = $asistencia !== null && $asistencia->hora_salida !== null;

            if (!$tieneEntrada) {
                // firstOrCreate() por la misma razón que en registrarAsistencia(): dos
                // pushes casi simultáneos del mismo usuario no deben duplicar la fila.
                $asistencia = AsistenciaGestion::firstOrCreate(
                    ['id_user' => $idUsuario, 'fecha_asistencia' => $fecha],
                    ['hora_asistencia' => $hora, 'fechareg' => now()]
                );

                if (!$asistencia->wasRecentlyCreated) {
                    return [
                        'error' => false,
                        'message' => 'Ya existe un registro de entrada para este usuario en esta fecha',
                        'data' => null,
                    ];
                }

                $this->notificarLlegadaTardeDespuesDeResponder($asistencia);

                return [
                    'error' => false,
                    'message' => 'Entrada registrada correctamente',
                    'data' => array_merge($asistencia->toArray(), ['tipo' => 'Entry']),
                ];
            }

            if ($tieneSalida) {
                return [
                    'error' => false,
                    'message' => 'Ya existe un registro de salida para este usuario en esta fecha',
                    'data' => null,
                ];
            }

            $horaMinimaSalida = $this->horaMinimaSalidaParaUsuario($idUsuario);

            if ($hora < $horaMinimaSalida) {
                return [
                    'error' => false,
                    'message' => 'La salida no se puede marcar antes de las ' . $horaMinimaSalida,
                    'data' => null,
                ];
            }

            // Guard atómico: si otro proceso (reintento del dispositivo) ya registró
            // la salida entre el check de arriba y este update, whereNull no afecta
            // ninguna fila y quedamos en el mismo caso "ya existe salida".
            $actualizado = AsistenciaGestion::where('id', $asistencia->id)
                ->whereNull('hora_salida')
                ->update(['hora_salida' => $hora]);

            if ($actualizado === 0) {
                return [
                    'error' => false,
                    'message' => 'Ya existe un registro de salida para este usuario en esta fecha',
                    'data' => null,
                ];
            }

            $asistencia->refresh();

            return [
                'error' => false,
                'message' => 'Salida registrada correctamente',
                'data' => array_merge($asistencia->toArray(), ['tipo' => 'Exit']),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al registrar marcación de asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar la marcación',
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

            if (!empty($filtros['id_nivel'])) {
                $query->porNivel($filtros['id_nivel']);
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

            foreach ($data['data'] as &$fila) {
                if (isset($fila['usuario']['perfil'])) {
                    $fila['usuario']['grupo'] = $this->grupoLabel((int) $fila['usuario']['perfil']);
                }
            }
            unset($fila);

            // "Faltó" solo tiene sentido para un día puntual: sin una fecha exacta no
            // hay un único universo de usuarios esperados contra el cual comparar.
            if (!empty($filtros['fecha'])) {
                $data['faltantes'] = $this->obtenerFaltantesDelDia(
                    $filtros['fecha'],
                    $filtros['id_nivel'] ?? null,
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
     * El correo se envía DESPUÉS de responder al dispositivo (terminating): el push de
     * Hikvision no debe esperar al SMTP, y un fallo de correo nunca debe afectar el
     * registro de la asistencia.
     */
    private function notificarLlegadaTardeDespuesDeResponder(AsistenciaGestion $asistencia): void
    {
        app()->terminating(function () use ($asistencia) {
            try {
                $this->notificarLlegadaTarde($asistencia);
            } catch (\Throwable $e) {
                Log::error('Error al notificar llegada tarde de trabajador', [
                    'id_asistencia' => $asistencia->id,
                    'error' => $e->getMessage(),
                ]);

                try {
                    $asistencia->update(['correo_llegada_tarde' => 'fallido', 'correo_llegada_tarde_at' => now()]);
                } catch (\Throwable) {
                    // sin más que hacer: el error ya quedó en el log
                }
            }
        });
    }

    /**
     * Aviso por correo de una entrada tarde recién registrada (ver la config en
     * configuracion_asistencia): al propio trabajador y, en un solo correo, a Recursos
     * Humanos y/o al coordinador del mismo nivel del trabajador (cada uno un toggle
     * independiente, ya no un selector libre de perfiles). El coordinador se resuelve con
     * Usuario::correosPorPerfilesYNivel, el mismo mecanismo que ya usan
     * PermisosLicenciasServices y ProcesoCompra\SolicitudesServices para enrutar avisos al
     * coordinador de nivel — Recursos Humanos, en cambio, no se acota por nivel (es
     * transversal a todo el colegio).
     */
    private function notificarLlegadaTarde(AsistenciaGestion $asistencia): void
    {
        $config = ConfiguracionAsistencia::find(self::ID_CONFIG);

        if (!$config || !$config->notificar_llegada_tarde) {
            return;
        }

        $asistencia->load('usuario');
        $usuario = $asistencia->usuario;

        if (!$usuario || !$asistencia->esTardanza()) {
            return;
        }

        $fecha = $asistencia->fecha_asistencia->toDateString();
        $hora = substr((string) $asistencia->hora_asistencia->format('H:i:s'), 0, 5);
        $nombre = trim("{$usuario->nombre} {$usuario->apellido}");

        $correosDestino = [];
        $nombresDestino = [];

        if ($config->notificar_recursos_humanos) {
            $correosRH = Usuario::where('estado', 'activo')
                ->where('perfil', self::PERFIL_RECURSOS_HUMANOS)
                ->where('id_user', '!=', $usuario->id_user)
                ->whereNotNull('correo')
                ->pluck('correo')
                ->all();

            if ($correosRH) {
                $correosDestino = array_merge($correosDestino, $correosRH);
                $nombresDestino[] = 'Recursos Humanos';
            }
        }

        if ($config->notificar_coordinador_nivel) {
            $correosCoordinador = array_diff(
                Usuario::correosPorPerfilesYNivel([self::PERFIL_COORDINADOR], $usuario->id_nivel, soloActivos: true),
                [$usuario->correo],
            );

            if ($correosCoordinador) {
                $correosDestino = array_merge($correosDestino, $correosCoordinador);
                $nombresDestino[] = 'el coordinador de nivel';
            }
        }

        $correosDestino = array_values(array_unique($correosDestino));

        if (count($correosDestino) > self::MAX_DESTINATARIOS_LLEGADA_TARDE) {
            Log::warning('Aviso de llegada tarde omitido: demasiados destinatarios', [
                'id_nivel' => $usuario->id_nivel,
                'destinatarios' => count($correosDestino),
            ]);
            $correosDestino = [];
            $nombresDestino = [];
        }

        $enviaTrabajador = $config->notificar_llegada_tarde_trabajador && $usuario->correo;
        $destinos = array_merge($enviaTrabajador ? ['el propio trabajador'] : [], $nombresDestino);
        $lista = $destinos ? implode(', ', $destinos) : 'nadie más';

        $resultados = [];

        if ($enviaTrabajador) {
            $resultados[] = $this->mailService->sendGeneric(
                $usuario->correo,
                'Llegada tarde registrada',
                "Hola {$nombre},\n\nTe recordamos la importancia de cumplir con la jornada laboral para el correcto funcionamiento de nuestra institución.\n\nSi estás ingresando fuera de tu horario, te invitamos a ajustar tu llegada. Nota: Si cuentas con un permiso parcial o previo autorizado, por favor haz caso omiso de este aviso.\n\n¡Tu puntualidad cuenta!"
            );
        }

        if ($correosDestino) {
            $resultados[] = $this->mailService->sendGeneric(
                $correosDestino,
                "Llegada tarde: {$nombre}",
                "{$nombre} (documento {$usuario->documento}) registró su llegada el {$fecha} a las {$hora}, fuera del horario de puntualidad ({$asistencia->puntualidad})."
            );
        }

        // Persistido para la columna "Correo" del reporte: 'omitido' si no hubo a quién avisar.
        $estado = match (true) {
            empty($resultados) => 'omitido',
            collect($resultados)->contains(fn ($r) => $r['error'] ?? true) => 'fallido',
            default => 'enviado',
        };
        $asistencia->update(['correo_llegada_tarde' => $estado, 'correo_llegada_tarde_at' => now()]);
    }

    /**
     * Usuarios activos, no excluidos de asistencia_gestion, que no tienen ninguna
     * llegada registrada en la fecha dada. Representan las "faltas" del día: no
     * existe una fila en asistencia_gestion para ellos (a diferencia de una llegada,
     * una falta no deja registro propio, se infiere por ausencia).
     */
    private function obtenerFaltantesDelDia(string $fecha, ?int $idNivel, ?int $idUsuario): array
    {
        $idsConAsistencia = AsistenciaGestion::whereDate('fecha_asistencia', $fecha)->pluck('id_user');

        $usuariosFaltantes = Usuario::where('estado', 'activo')
            ->whereNotIn('perfil', self::PERFILES_EXCLUIDOS_ASISTENCIA)
            ->whereNotIn('id_user', $idsConAsistencia)
            ->when($idNivel, fn ($q) => $q->where('id_nivel', $idNivel))
            ->when($idUsuario, fn ($q) => $q->where('id_user', $idUsuario))
            ->get(['id_user', 'nombre', 'apellido', 'documento', 'perfil', 'id_nivel']);

        if ($usuariosFaltantes->isEmpty()) {
            return [];
        }

        // Última llegada registrada de cada faltante (cualquier fecha anterior), para dar
        // contexto de cuándo fue la última vez que sí marcó (p. ej. "Última vez: 1 jul").
        $ultimasLlegadas = AsistenciaGestion::whereIn('id_user', $usuariosFaltantes->pluck('id_user'))
            ->selectRaw('id_user, MAX(fecha_asistencia) as ultima_fecha')
            ->groupBy('id_user')
            ->pluck('ultima_fecha', 'id_user');

        return $usuariosFaltantes
            ->map(function ($usuario) use ($fecha, $ultimasLlegadas) {
                $usuario->grupo = $this->grupoLabel((int) $usuario->perfil);

                return [
                    'id' => null,
                    'id_user' => $usuario->id_user,
                    'fecha_asistencia' => $fecha,
                    'hora_asistencia' => null,
                    'fechareg' => null,
                    'puntualidad' => null,
                    'estado' => 'faltó',
                    'ultima_llegada' => $ultimasLlegadas[$usuario->id_user] ?? null,
                    'usuario' => $usuario,
                ];
            })
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

            if (!empty($filtros['id_usuario'])) {
                $porDia->where('ag.id_user', $filtros['id_usuario']);
            }

            $porDia = $porDia->groupBy('ag.fecha_asistencia')
                ->orderBy('ag.fecha_asistencia')
                ->get();

            $porPerfil = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select('u.perfil', DB::raw('COUNT(DISTINCT ag.id_user) as total_usuarios'))
                ->whereBetween('ag.fecha_asistencia', [$rangoFechas['desde'], $rangoFechas['hasta']])
                ->when(!empty($filtros['id_usuario']), fn ($q) => $q->where('ag.id_user', $filtros['id_usuario']))
                ->groupBy('u.perfil')
                ->get();

            $porHora = DB::table('asistencia_gestion')
                ->select(
                    DB::raw('HOUR(hora_asistencia) as hora'),
                    DB::raw('COUNT(*) as total')
                )
                ->whereBetween('fecha_asistencia', [$rangoFechas['desde'], $rangoFechas['hasta']])
                ->when(!empty($filtros['id_usuario']), fn ($q) => $q->where('id_user', $filtros['id_usuario']))
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

    public function topUsuariosLlegadasTarde(array $filtros): array
    {
        try {
            $limite = $filtros['top'] ?? 10;
            // Mismo corte institucional configurable que ya usa
            // HikvisionController::registrarLlegadaTardeSiAplica para decidir si una
            // marcación es tarde — no un literal propio de este método. No se acepta
            // override por request: antes tenía un `07:15:00` hardcodeado que no se
            // movía aunque el admin cambiara la hora límite real desde el panel
            // (hoy 07:05:00, ver configuracion_llegadas_tarde), desalineando este top del
            // resto del sistema.
            $horaLimite = ConfiguracionLlegadasTarde::find(1)?->hora_limite ?? '07:15:00';

            $query = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select(
                    'ag.id_user',
                    'u.nombre',
                    'u.perfil',
                    DB::raw('COUNT(*) as total_llegadas_tarde')
                )
                ->where('ag.hora_asistencia', '>', $horaLimite)
                ->where('ag.revocado', false)
                ->groupBy('ag.id_user', 'u.nombre', 'u.perfil');

            if (!empty($filtros['id_perfil'])) {
                $query->where('u.perfil', $filtros['id_perfil']);
            }

            if (!empty($filtros['id_usuario'])) {
                $query->where('ag.id_user', $filtros['id_usuario']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->where('ag.fecha_asistencia', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->where('ag.fecha_asistencia', '<=', $filtros['fecha_hasta']);
            }

            $resultados = $query->orderByDesc('total_llegadas_tarde')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Top de llegadas tardías obtenido',
                'data' => $resultados,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener top de llegadas tardías');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener top de llegadas tardías',
                'data' => null,
            ];
        }
    }

    public function distribucionHorasLlegada(array $filtros): array
    {
        try {
            $query = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select(
                    DB::raw('HOUR(ag.hora_asistencia) as hora'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(DISTINCT ag.id_user) as usuarios_unicos')
                )
                ->groupBy(DB::raw('HOUR(ag.hora_asistencia)'));

            if (!empty($filtros['id_perfil'])) {
                $query->where('u.perfil', $filtros['id_perfil']);
            }

            if (!empty($filtros['id_usuario'])) {
                $query->where('ag.id_user', $filtros['id_usuario']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->where('ag.fecha_asistencia', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->where('ag.fecha_asistencia', '<=', $filtros['fecha_hasta']);
            }

            $resultados = $query->orderBy('hora')->get();

            return [
                'error' => false,
                'message' => 'Distribución de horas obtenida',
                'data' => $resultados,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener distribución de horas');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener distribución de horas',
                'data' => null,
            ];
        }
    }

    public function promedioHoraLlegadaPorUsuario(array $filtros): array
    {
        try {
            $query = DB::table('asistencia_gestion as ag')
                ->join('usuarios as u', 'u.id_user', '=', 'ag.id_user')
                ->select(
                    'ag.id_user',
                    DB::raw("CONCAT(u.nombre, ' ', u.apellido) as nombre_completo"),
                    'u.perfil',
                    DB::raw('COUNT(*) as total_marcaciones'),
                    DB::raw('MIN(ag.hora_asistencia) as primera_llegada'),
                    DB::raw('MAX(ag.hora_asistencia) as ultima_llegada'),
                    DB::raw('AVG(TIME_TO_SEC(ag.hora_asistencia)) as promedio_segundos')
                )
                ->groupBy('ag.id_user', 'nombre_completo', 'u.perfil');

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

            $perPage = $filtros['per_page'] ?? 20;
            $resultados = $query->orderBy('nombre_completo')
                ->paginate($perPage);

            $data = $resultados->through(fn ($r) => [
                'id_user' => $r->id_user,
                'nombre' => $r->nombre_completo,
                'perfil' => $r->perfil,
                'total_marcaciones' => (int) $r->total_marcaciones,
                'primera_llegada' => $r->primera_llegada,
                'ultima_llegada' => $r->ultima_llegada,
                'promedio_hora' => $this->segundosATiempo((int) round($r->promedio_segundos)),
            ]);

            return [
                'error' => false,
                'message' => 'Promedio de hora de llegada obtenido',
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener promedio de hora de llegada');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener promedio de hora de llegada',
                'data' => null,
            ];
        }
    }

    public function ultimosRegistrosUsuario(int $idUsuario, int $limite = 30): array
    {
        try {
            $registros = AsistenciaGestion::with('usuario')
                ->porUsuario($idUsuario)
                ->orderBy('fecha_asistencia', 'desc')
                ->orderBy('hora_asistencia', 'desc')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Últimos registros obtenidos',
                'data' => $registros,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener últimos registros');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener últimos registros',
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

    public function actualizarObservacion(int $id, ?string $observacion): array
    {
        try {
            $asistencia = AsistenciaGestion::find($id);

            if (!$asistencia) {
                return [
                    'error' => true,
                    'message' => 'Asistencia no encontrada',
                    'data' => null,
                    'status' => 404,
                ];
            }

            $asistencia->update(['observacion' => $observacion]);

            return [
                'error' => false,
                'message' => 'Observación actualizada',
                'data' => $asistencia->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar observación de asistencia');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar observación de asistencia',
                'data' => null,
            ];
        }
    }

    /**
     * Revoca la llegada tarde de un trabajador: el registro de asistencia se conserva
     * (no se borra, la hora real de marcación queda intacta) pero deja de contar en
     * topUsuariosLlegadasTarde() — mismo patrón que
     * LlegadasTardeEstudiantes\LlegadasTarde::revocarLlegadaTarde() para estudiantes. Solo
     * RH y Super Admin pueden llamar esto (ver gate en AsistenciaGestionController). La
     * observación es opcional y, si se manda, reemplaza la existente (para dejar el motivo
     * de la revocación); si no, se conserva la que ya tenía el registro.
     */
    public function revocarLlegadaTarde(int $id, ?string $observacion = null): array
    {
        try {
            $asistencia = AsistenciaGestion::find($id);

            if (!$asistencia) {
                return [
                    'error' => true,
                    'message' => 'Asistencia no encontrada',
                    'data' => null,
                    'status' => 404,
                ];
            }

            if ($asistencia->revocado) {
                return [
                    'error' => false,
                    'message' => 'La llegada tarde ya estaba revocada',
                    'data' => $asistencia->toArray(),
                ];
            }

            $asistencia->update(array_merge(
                ['revocado' => true],
                $observacion !== null ? ['observacion' => $observacion] : []
            ));

            return [
                'error' => false,
                'message' => 'Llegada tarde revocada correctamente',
                'data' => $asistencia->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al revocar la llegada tarde');
            return [
                'error' => true,
                'message' => 'Error en el servidor al revocar la llegada tarde',
                'data' => null,
            ];
        }
    }

    /**
     * Cierra automáticamente las asistencias del día que tienen entrada pero no salida y ya
     * pasaron la hora_cierre_automatico del horario aplicable al usuario, por grupo o global
     * si no hay uno específico — pensado para correr periódicamente desde el scheduler (ver
     * CerrarAsistenciasVencidasCommand). El valor que se registra como hora_salida sigue
     * siendo hora_salida_esperada, no la hora de cierre. Sin horario resoluble para el
     * usuario, o sin hora_cierre_automatico configurada en el horario aplicable, esa fila
     * se deja igual (no hay cierre automático).
     */
    public function cerrarAsistenciasVencidas(): array
    {
        try {
            $abiertas = AsistenciaGestion::with('usuario')
                ->where('fecha_asistencia', now()->toDateString())
                ->whereNotNull('hora_asistencia')
                ->whereNull('hora_salida')
                ->get();

            $horaActual = now()->format('H:i:s');
            $cerradas = [];

            foreach ($abiertas as $asistencia) {
                $perfil = $asistencia->usuario?->perfil;
                $grupoId = $perfil !== null ? (hikvisionattendanceService::GROUP_ID_POR_PERFIL[(int) $perfil] ?? null) : null;
                $horario = AsistenciaGestion::horarioAplicable($grupoId);

                // Sin hora_cierre_automatico configurada, el horario no tiene cierre
                // automático: no se cae a hora_salida_esperada como trigger implícito.
                if (!$horario || !$horario->hora_cierre_automatico || $horaActual < $horario->hora_cierre_automatico) {
                    continue;
                }

                $horaEsperada = substr($horario->hora_salida_esperada, 0, 5);

                $asistencia->update([
                    'hora_salida' => $horario->hora_salida_esperada,
                    'observacion' => "Salida marcada automáticamente: el usuario no registró su salida antes de la hora esperada ({$horaEsperada}).",
                ]);

                $usuario = $asistencia->usuario;
                $nombreCompleto = trim(($usuario->nombre ?? '') . ' ' . ($usuario->apellido ?? ''));
                $fecha = $asistencia->fecha_asistencia->format('Y-m-d');

                if ($horario->notificar_trabajador && $usuario?->correo) {
                    $this->mailService->sendGeneric(
                        $usuario->correo,
                        'Salida marcada automáticamente',
                        "Hola {$nombreCompleto},\n\nNo registraste tu salida el {$fecha} antes de la hora esperada ({$horaEsperada}), así que el sistema la marcó automáticamente a esa hora.\n\nSi esto es un error, comunícate con Recursos Humanos."
                    );
                }

                if ($horario->notificar_rh) {
                    $this->mailService->sendGeneric(
                        $this->mailTo,
                        'Salida automática registrada',
                        "El usuario {$nombreCompleto} (documento {$usuario->documento}) no registró su salida el {$fecha} antes de la hora esperada ({$horaEsperada}). El sistema la marcó automáticamente a esa hora."
                    );
                }

                $cerradas[] = ['id_user' => $asistencia->id_user, 'nombre' => $nombreCompleto, 'fecha' => $fecha];
            }

            return [
                'error' => false,
                'message' => count($cerradas) . ' asistencia(s) cerrada(s) automáticamente',
                'data' => ['cerradas' => count($cerradas), 'detalle' => $cerradas],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cerrar asistencias vencidas');
            return [
                'error' => true,
                'message' => 'Error en el servidor al cerrar asistencias vencidas',
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

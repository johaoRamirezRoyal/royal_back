<?php

namespace App\Services\LlegadasTardeEstudiantes;

use App\Mail\LlegadaTardeAvisoInternoMail;
use App\Mail\LlegadaTardeMail;
use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\LlegadasTarde\ConfiguracionLlegadasTarde;
use App\Models\LlegadasTarde\LlegadasTarde as ModelsLlegadasTarde;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use App\Services\Service;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class LlegadasTarde extends Service
{
    private const PERFIL_DIRECTIVO_DOCENTE = 20;

    // `periodo_academico.nombre` se guarda como texto ("Primer periodo", "Segundo
    // periodo"...) pero en los correos se muestra como número ("Periodo 1") — ver
    // numeroPeriodo().
    private const ORDINALES_PERIODO = [
        'primer' => 1,
        'segundo' => 2,
        'tercer' => 3,
        'cuarto' => 4,
        'quinto' => 5,
        'sexto' => 6,
    ];

    private array $mailToVicerrectoria = ['alfredo.canas@royalschool.edu.co'];

    public function __construct(
        private MailService $mailService,
        private WhatsAppService $whatsAppService
    ) {}

    public function agregarLlegadaTarde(int $id_alumno, string $fecha, string $hora): array
    {
        try {
            $yaRegistrada = ModelsLlegadasTarde::where('id_alumno', $id_alumno)
                ->where('fecha', $fecha)
                ->exists();

            if ($yaRegistrada) {
                return [
                    'error' => false,
                    'message' => 'El alumno ya tiene una llegada tarde registrada hoy',
                    'data' => []
                ];
            }

            $periodo_academico = $this->periodoVigente();

            if (!$periodo_academico) {
                return [
                    'error' => true,
                    'message' => "No se encontró un periodo académico disponible para registrar la llegada tarde",
                    'data' => []
                ];
            }

            $totalActual = ModelsLlegadasTarde::where('id_alumno', $id_alumno)
                ->where('id_periodo_academico', $periodo_academico->id)
                ->count();

            $cantidadLimite = ConfiguracionLlegadasTarde::find(1)?->cantidad_limite ?? 5;

            // El límite configurado se cuenta como "hasta acá se permite": con cantidad_limite=5
            // la 6ª llegada tarde en adelante se sigue registrando (las llegadas tarde no dejan
            // de contarse), pero cada una de ellas se marca como límite alcanzado y dispara la
            // carta formal de incumplimiento al acudiente en cada ocasión.
            $umbralLimite = $cantidadLimite > 0 ? $cantidadLimite + 1 : 0;

            // firstOrCreate() intenta el create() y, si choca con el índice único
            // (llegadas_tardes_alumno_fecha_unique), reconsulta en vez de fallar:
            // así dos pushes casi simultáneos del mismo alumno (p. ej. un
            // reintento del dispositivo) no pueden duplicar la fila.
            $llegadaTarde = ModelsLlegadasTarde::firstOrCreate(
                ['id_alumno' => $id_alumno, 'fecha' => $fecha],
                ['hora' => $hora, 'id_periodo_academico' => $periodo_academico->id]
            );

            if (!$llegadaTarde->wasRecentlyCreated) {
                return [
                    'error' => false,
                    'message' => 'El alumno ya tiene una llegada tarde registrada hoy',
                    'data' => []
                ];
            }

            $totalEnPeriodo = $totalActual + 1;
            $limiteAlcanzado = $umbralLimite > 0 && $totalEnPeriodo >= $umbralLimite;
            // Solo la llegada que cruza el umbral por primera vez escala a Vicerrectoría; las
            // siguientes (7ª, 8ª...) siguen mandando la carta al acudiente pero no vuelven a
            // notificar internamente, para no repetir la escalación en cada reincidencia.
            $primeraVezLimiteAlcanzado = $umbralLimite > 0 && $totalEnPeriodo === $umbralLimite;
            $llegadaTarde->update(['limite_alcanzado' => $limiteAlcanzado]);

            $enviado = $this->enviarNotificacionLlegadaTarde($llegadaTarde, $totalEnPeriodo, $limiteAlcanzado, avisarVicerrectoria: $primeraVezLimiteAlcanzado);
            $llegadaTarde->update(['enviado' => $enviado]);

            return [
                'error' => false,
                'message' => "Llegada tarde creada correctamente",
                'data' => array_merge($llegadaTarde->toArray(), ['total_llegadas_tarde_periodo' => $totalEnPeriodo])
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al agregar la llegada tarde");
            return [
                'error' => true,
                'message' => "Error al agregar la llegada tarde",
                'data' => []
            ];
        }
    }

    public function obtenerLlegadasTarde(?int $id_periodo_academico = null, ?int $id_alumno = null, ?string $fecha = null): array
    {
        try {
            if ($id_periodo_academico === null) {

                $periodo = $this->periodoVigente();

                if (!$periodo) {
                    return [
                        'error' => true,
                        'message' => 'No existe un período académico activo',
                        'data' => []
                    ];
                }

                $id_periodo_academico = $periodo->id;
            }

            $llegadas_tarde = ModelsLlegadasTarde::where('id_periodo_academico', $id_periodo_academico)
                ->with([
                    'alumno' => fn($q) => $q->select('id_user', 'nombre', 'apellido', 'correo', 'id_curso')
                                           ->with('cursoRelacion:id,nombre'),
                    'periodoAcademico:id,fecha_inicio,fecha_fin,activo',
                ])
                ->when($id_alumno !== null, function ($query) use ($id_alumno) {
                    $query->where('id_alumno', $id_alumno);
                })
                ->when($fecha !== null, function ($query) use ($fecha) {
                    $query->where('fecha', $fecha);
                })
                ->get();

            if ($llegadas_tarde->isEmpty()) {
                return [
                    'error' => false,
                    'message' => 'No se encontraron llegadas tarde para el periodo académico',
                    'data' => []
                ];
            }

            // Conteo por alumno dentro de este período (no filtrado por id_alumno: si se
            // pidió un alumno puntual solo hay una llave, pero si se listó el período
            // completo cada fila trae cuántas lleva SU alumno, no el total de todos).
            $conteoPorAlumno = ModelsLlegadasTarde::where('id_periodo_academico', $id_periodo_academico)
                ->selectRaw('id_alumno, count(*) as total')
                ->groupBy('id_alumno')
                ->pluck('total', 'id_alumno');

            $llegadas_tarde->each(
                fn($registro) => $registro->total_llegadas_tarde_periodo = $conteoPorAlumno[$registro->id_alumno] ?? 1
            );

            return [
                'error' => false,
                'message' => 'Obtenidos los llegadas tarde',
                'data' => [
                    'total_llegadas_tarde' => $llegadas_tarde->count(),
                    'registros' => $llegadas_tarde
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener las llegadas tarde");
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener las llegadas tarde",
                'data' => []
            ];
        }
    }

    /**
     * Resumen del período académico para un dashboard: totales, top de estudiantes,
     * desglose por curso y por día. Si no se indica id_periodo_academico usa el vigente
     * (mismo criterio que obtenerLlegadasTarde/agregarLlegadaTarde).
     */
    public function dashboardLlegadasTarde(?int $id_periodo_academico = null): array
    {
        try {
            $periodo = $id_periodo_academico
                ? PeriodoAcademico::find($id_periodo_academico)
                : $this->periodoVigente();

            if (!$periodo) {
                return [
                    'error' => true,
                    'message' => $id_periodo_academico
                        ? 'No se encontró el período académico indicado'
                        : 'No existe un período académico activo',
                    'data' => []
                ];
            }

            $config = ConfiguracionLlegadasTarde::find(1);
            $cantidadLimite = $config?->cantidad_limite ?? 5;

            $totalLlegadasTarde = ModelsLlegadasTarde::where('id_periodo_academico', $periodo->id)->count();

            $totalEstudiantesAfectados = ModelsLlegadasTarde::where('id_periodo_academico', $periodo->id)
                ->distinct()
                ->count('id_alumno');

            $totalEstudiantesLimiteAlcanzado = ModelsLlegadasTarde::where('id_periodo_academico', $periodo->id)
                ->where('limite_alcanzado', true)
                ->distinct()
                ->count('id_alumno');

            $topEstudiantes = DB::table('llegadas_tardes as lt')
                ->join('usuarios as u', 'u.id_user', '=', 'lt.id_alumno')
                ->leftJoin('curso as c', 'c.id', '=', 'u.id_curso')
                ->where('lt.id_periodo_academico', $periodo->id)
                ->select(
                    'lt.id_alumno',
                    'u.nombre',
                    'u.apellido',
                    'u.documento',
                    'c.nombre as curso',
                    DB::raw('count(*) as total_llegadas_tarde'),
                    DB::raw('max(case when lt.limite_alcanzado then 1 else 0 end) as limite_alcanzado')
                )
                ->groupBy('lt.id_alumno', 'u.nombre', 'u.apellido', 'u.documento', 'c.nombre')
                ->orderByDesc('total_llegadas_tarde')
                ->limit(10)
                ->get();

            $porCurso = DB::table('llegadas_tardes as lt')
                ->join('usuarios as u', 'u.id_user', '=', 'lt.id_alumno')
                ->leftJoin('curso as c', 'c.id', '=', 'u.id_curso')
                ->where('lt.id_periodo_academico', $periodo->id)
                ->select('c.id as id_curso', 'c.nombre as curso', DB::raw('count(*) as total'))
                ->groupBy('c.id', 'c.nombre')
                ->orderByDesc('total')
                ->get();

            $porDia = ModelsLlegadasTarde::where('id_periodo_academico', $periodo->id)
                ->select('fecha', DB::raw('count(*) as total'))
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            return [
                'error' => false,
                'message' => 'Dashboard de llegadas tarde obtenido',
                'data' => [
                    'periodo_academico' => [
                        'id' => $periodo->id,
                        'nombre' => $periodo->nombre,
                        'fecha_inicio' => $periodo->fecha_inicio,
                        'fecha_fin' => $periodo->fecha_fin,
                    ],
                    'configuracion' => [
                        'hora_limite' => $config?->hora_limite,
                        'cantidad_limite' => $cantidadLimite,
                        'umbral_bloqueo' => $cantidadLimite > 0 ? $cantidadLimite + 1 : 0,
                    ],
                    'resumen' => [
                        'total_llegadas_tarde' => $totalLlegadasTarde,
                        'total_estudiantes_afectados' => $totalEstudiantesAfectados,
                        'total_estudiantes_limite_alcanzado' => $totalEstudiantesLimiteAlcanzado,
                    ],
                    'top_estudiantes' => $topEstudiantes,
                    'por_curso' => $porCurso,
                    'por_dia' => $porDia,
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener el dashboard de llegadas tarde");
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener el dashboard de llegadas tarde",
                'data' => []
            ];
        }
    }

    public function eliminarLlegadaTarde(array $ids_llegadas_tarde): array
    {
        try {
            $registros = ModelsLlegadasTarde::whereIn('id', $ids_llegadas_tarde)->get();

            if ($registros->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron llegadas tarde para los IDs: ' . implode(', ', $ids_llegadas_tarde),
                    'data' => []
                ];
            }

            $eliminados = ModelsLlegadasTarde::whereIn('id', $ids_llegadas_tarde)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se eliminaron los registros de las llegadas tarde',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha eliminado esta llegada tarde",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al eliminar la llegada tarde");

            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de eliminar la llegada tarde",
                'data' => []
            ];
        }
    }

    /**
     * Reenvía manualmente la notificación de una llegada tarde ya registrada (al
     * estudiante y a sus acudientes) y actualiza `enviado` según el resultado. No repite
     * el aviso a Vicerrectoría aunque `limite_alcanzado` esté en true — ese aviso es una
     * escalación puntual del momento del registro, no algo que deba reenviarse cada vez
     * que alguien reintenta el correo principal.
     */
    public function reenviarCorreo(int $id): array
    {
        try {
            $llegadaTarde = ModelsLlegadasTarde::find($id);

            if (!$llegadaTarde) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la llegada tarde con ID: ' . $id,
                    'data' => []
                ];
            }

            $totalEnPeriodo = ModelsLlegadasTarde::where('id_alumno', $llegadaTarde->id_alumno)
                ->where('id_periodo_academico', $llegadaTarde->id_periodo_academico)
                ->count();

            $enviado = $this->enviarNotificacionLlegadaTarde(
                $llegadaTarde,
                $totalEnPeriodo,
                (bool) $llegadaTarde->limite_alcanzado,
                avisarVicerrectoria: false
            );

            $llegadaTarde->update(['enviado' => $enviado]);

            return [
                'error' => false,
                'message' => $enviado
                    ? 'Correo enviado correctamente'
                    : 'No se pudo enviar el correo. Verifique que el estudiante o sus acudientes tengan correo registrado.',
                'data' => ['enviado' => $enviado]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al reenviar el correo de la llegada tarde");

            return [
                'error' => true,
                'message' => "Error en el servidor al reenviar el correo",
                'data' => []
            ];
        }
    }

    /**
     * Período académico vigente: de los marcados activo=true, el que aún no ha
     * terminado (fecha_fin >= hoy) con la fecha_inicio más temprana. No basta con
     * "activo=true" solo — nada impide que queden varios períodos marcados activos
     * a la vez (el cierre automático de vencidos corre una vez al día), y ahí tomar
     * el de fecha_inicio más reciente devuelve un período futuro en vez del actual.
     * Tampoco exige que hoy caiga dentro de [fecha_inicio, fecha_fin]: si aún no
     * arrancó el primer período del ciclo, sigue siendo "el vigente" hasta que
     * empiece uno.
     */
    private function periodoVigente(): ?PeriodoAcademico
    {
        return PeriodoAcademico::where('activo', true)
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->orderBy('fecha_inicio')
            ->first();
    }

    /**
     * "Primer periodo" → "1", "Segundo periodo" → "2", etc. (ver ORDINALES_PERIODO) —
     * en los correos el periodo se muestra como número ("Periodo 1"), no con el nombre
     * completo tal cual está en `periodo_academico.nombre`. Si el nombre no matchea
     * ningún ordinal conocido, se muestra tal cual en vez de fallar silenciosamente.
     */
    private function numeroPeriodo(?PeriodoAcademico $periodo, int $idPeriodoFallback): string
    {
        if (!$periodo) {
            return "#{$idPeriodoFallback}";
        }

        $primeraPalabra = strtolower(strtok(trim((string) $periodo->nombre), ' '));

        return isset(self::ORDINALES_PERIODO[$primeraPalabra])
            ? (string) self::ORDINALES_PERIODO[$primeraPalabra]
            : ($periodo->nombre ?: "#{$idPeriodoFallback}");
    }

    /**
     * Notifica por correo y WhatsApp, con el mismo contenido (formato oficial firmado
     * por Vicerrectoría) dirigido a "padre de familia y estudiante", enviado tanto al
     * correo/celular del alumno... el correo va también al alumno, el WhatsApp solo a
     * los acudientes activos (`estudiantes_padres.celular`) — un menor normalmente no
     * tiene número propio de WhatsApp registrado para esto. El texto varía según
     * `cartaLlegadaTarde()`. Se llama en cada llegada tarde que se registra, incluidas
     * las que ocurren después de superado el límite (esas repiten la notificación de
     * incumplimiento cada vez). Devuelve si el envío de correo tuvo éxito (lo que
     * persiste en `enviado` el caller — este método no toca la fila, solo envía); el
     * resultado del WhatsApp no se propaga porque `enviado` en la fila históricamente
     * solo trackea el correo. Si `$avisarVicerrectoria` es true, además notifica a
     * Vicerrectoría por separado con `LlegadaTardeAvisoInternoMail` (mismo estilo
     * visual, pero ese envío no cuenta para el resultado devuelto: es una escalación
     * interna, no la carta al acudiente) — el caller solo pasa true la primera vez que
     * se cruza el límite, para no repetir la escalación en cada reincidencia.
     * `MailService::send()`/`WhatsAppService::sendTemplate()` atrapan sus propios
     * errores, así que un fallo de envío no afecta el registro de la llegada tarde (ya
     * se guardó antes de llamar este método).
     */
    private function enviarNotificacionLlegadaTarde(
        ModelsLlegadasTarde $llegadaTarde,
        int $totalEnPeriodo,
        bool $limiteAlcanzado,
        bool $avisarVicerrectoria = true
    ): bool {
        $estudiante = Usuario::with('cursoRelacion')->find($llegadaTarde->id_alumno);

        if (!$estudiante) {
            return false;
        }

        $correoEstudiante = ($estudiante->correo && $estudiante->estado === 'activo')
            ? [$estudiante->correo]
            : [];

        $correosAcudientes = Usuario::whereIn(
            'id_user',
            EstudiantesPadre::where('id_estudiante', $llegadaTarde->id_alumno)->where('activo', 1)->pluck('id_acudiente')
        )
            ->where('estado', 'activo')
            ->whereNotNull('correo')
            ->pluck('correo')
            ->filter()
            ->all();

        $telefonosAcudientes = EstudiantesPadre::where('id_estudiante', $llegadaTarde->id_alumno)
            ->where('activo', 1)
            ->whereNotNull('celular')
            ->pluck('celular')
            ->filter()
            ->all();

        $destinatarios = array_values(array_unique(array_merge($correoEstudiante, $correosAcudientes)));

        $carta = $this->cartaLlegadaTarde($estudiante, $llegadaTarde, $totalEnPeriodo, $limiteAlcanzado);

        $enviado = false;

        if (!empty($destinatarios)) {
            $enviado = $this->mailService->send($destinatarios, $carta);
        }

        if (!empty($telefonosAcudientes)) {
            $this->whatsAppService->sendTemplateToMany(
                $telefonosAcudientes,
                $this->plantillaWhatsApp($carta),
                $this->parametrosWhatsApp($carta)
            );
        }

        if ($limiteAlcanzado && $avisarVicerrectoria) {
            $correosDirectivoDocente = Usuario::where('perfil', self::PERFIL_DIRECTIVO_DOCENTE)
                ->where('estado', 'activo')
                ->whereNotNull('correo')
                ->pluck('correo')
                ->filter()
                ->all();

            $this->mailService->send(
                array_values(array_unique(array_merge($this->mailToVicerrectoria, $correosDirectivoDocente))),
                new LlegadaTardeAvisoInternoMail(
                    nombreEstudiante: trim("{$estudiante->nombre} {$estudiante->apellido}"),
                    documento: (string) $estudiante->documento,
                    grado: $estudiante->cursoRelacion?->nombre ?? 'Sin curso asignado',
                    totalEnPeriodo: $totalEnPeriodo,
                    periodo: $this->numeroPeriodo($llegadaTarde->periodoAcademico, $llegadaTarde->id_periodo_academico),
                    fecha: Carbon::parse($llegadaTarde->fecha)->locale('es')->translatedFormat('d \d\e F \d\e Y'),
                    hora: substr($llegadaTarde->hora, 0, 5),
                )
            );
        }

        return $enviado;
    }

    /**
     * Carta oficial (mismo estilo visual que el correo de verificación de admisiones —
     * ver resources/views/emails/llegadaTarde.blade.php — firmada por Vicerrectoría) que
     * se envía al alumno y a sus acudientes por cada llegada tarde. Tres variantes: la
     * normal (llegadas 1 a `cantidad_limite - 1`) solo informa; la de advertencia (llegada
     * número `cantidad_limite`, ej. la 5ª con la config por defecto) avisa que una llegada
     * tarde más implicará no poder ingresar; la de `$limiteAlcanzado` (llegada
     * `cantidad_limite + 1` en adelante — 6ª, 7ª...) es la carta formal de incumplimiento,
     * que se repite en cada llegada tarde posterior al límite.
     */
    private function cartaLlegadaTarde(
        Usuario $estudiante,
        ModelsLlegadasTarde $llegadaTarde,
        int $totalEnPeriodo,
        bool $limiteAlcanzado
    ): LlegadaTardeMail {
        $nombreCompleto = trim("{$estudiante->nombre} {$estudiante->apellido}");
        $grado = $estudiante->cursoRelacion?->nombre ?? 'Sin curso asignado';
        $fecha = Carbon::parse($llegadaTarde->fecha)->locale('es')->translatedFormat('d \d\e F \d\e Y');
        $periodo = $this->numeroPeriodo($llegadaTarde->periodoAcademico, $llegadaTarde->id_periodo_academico);
        $cantidadLimite = ConfiguracionLlegadasTarde::find(1)?->cantidad_limite ?? 5;
        $advertencia = !$limiteAlcanzado && $cantidadLimite > 0 && $totalEnPeriodo >= $cantidadLimite;

        $asunto = match (true) {
            $limiteAlcanzado => 'Notificación de incumplimiento del límite de llegadas tarde',
            $advertencia => 'Advertencia por acumulación de llegadas tarde',
            default => 'Registro de llegada tarde',
        };

        return new LlegadaTardeMail(
            asunto: $asunto,
            nombreEstudiante: $nombreCompleto,
            grado: $grado,
            fecha: $fecha,
            totalEnPeriodo: $totalEnPeriodo,
            periodo: $periodo,
            limiteAlcanzado: $limiteAlcanzado,
            cantidadLimite: $cantidadLimite,
            advertencia: $advertencia,
        );
    }

    /**
     * Nombre de la plantilla de WhatsApp (Meta Cloud API) a usar según la misma
     * clasificación de tres niveles que `cartaLlegadaTarde()`. Los nombres están
     * configurados en services.whatsapp.template_* (.env) porque el nombre real en
     * Meta Business Manager puede diferir del sugerido aquí durante la revisión.
     */
    private function plantillaWhatsApp(LlegadaTardeMail $carta): string
    {
        return match (true) {
            $carta->limiteAlcanzado => config('services.whatsapp.template_limite', 'llegada_tarde_limite'),
            $carta->advertencia => config('services.whatsapp.template_advertencia', 'llegada_tarde_advertencia'),
            default => config('services.whatsapp.template_normal', 'llegada_tarde_normal'),
        };
    }

    /**
     * Parámetros posicionales {{1}}, {{2}}... para la plantilla de WhatsApp — deben
     * coincidir en cantidad y orden con el cuerpo de cada plantilla tal como fue
     * aprobada en Meta (ver docs/whatsapp-llegadas-tarde.md para el texto exacto
     * sometido a revisión).
     */
    private function parametrosWhatsApp(LlegadaTardeMail $carta): array
    {
        return match (true) {
            $carta->limiteAlcanzado => [$carta->nombreEstudiante, $carta->grado, $carta->cantidadLimite, $carta->fecha],
            $carta->advertencia => [$carta->nombreEstudiante, $carta->grado, $carta->totalEnPeriodo],
            default => [$carta->nombreEstudiante, $carta->grado, $carta->fecha, $carta->totalEnPeriodo],
        };
    }
}

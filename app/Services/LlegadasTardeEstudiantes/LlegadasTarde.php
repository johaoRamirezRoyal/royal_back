<?php

namespace App\Services\LlegadasTardeEstudiantes;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\LlegadasTarde\ConfiguracionLlegadasTarde;
use App\Models\LlegadasTarde\LlegadasTarde as ModelsLlegadasTarde;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class LlegadasTarde extends Service
{
    private const PERFIL_DIRECTIVO_DOCENTE = 20;

    private array $mailToVicerrectoria = ['hernando.ramirez@royalschool.edu.co'];

    public function __construct(private MailService $mailService) {}

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
            // la 6ª llegada tarde es la que se registra marcada como límite alcanzado (y la que
            // dispara el aviso a vicerrectoría); la 7ª ya no se registra.
            $umbralLimite = $cantidadLimite > 0 ? $cantidadLimite + 1 : 0;

            // Una vez el alumno supera el límite del período, no se siguen sumando llegadas
            // tarde adicionales (aunque el dispositivo o un registro manual lo intenten).
            if ($umbralLimite > 0 && $totalActual >= $umbralLimite) {
                return [
                    'error' => false,
                    'message' => 'El alumno ya alcanzó el límite de llegadas tarde del período académico; no se registran más',
                    'data' => ['total_llegadas_tarde_periodo' => $totalActual]
                ];
            }

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
            $llegadaTarde->update(['limite_alcanzado' => $limiteAlcanzado]);

            $this->notificarLlegadaTarde($llegadaTarde, $totalEnPeriodo, $limiteAlcanzado);

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
                ->count();

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
     * Notifica por correo al estudiante y a sus acudientes activos cada vez que se
     * registra una llegada tarde (siempre). Si con esta llegada el alumno alcanza la
     * cantidad_limite configurada para el período, además notifica a Vicerrectoría
     * (a partir de ahí ya no se registran más llegadas tarde en el período, así que
     * este aviso solo puede dispararse una vez por alumno por período).
     * sendGeneric() atrapa sus propios errores, así que un fallo de envío no afecta
     * el registro de la llegada tarde (ya se guardó antes de llamar este método).
     */
    private function notificarLlegadaTarde(ModelsLlegadasTarde $llegadaTarde, int $totalEnPeriodo, bool $limiteAlcanzado): void
    {
        $estudiante = Usuario::find($llegadaTarde->id_alumno);

        if (!$estudiante) {
            return;
        }

        $nombreCompleto = trim("{$estudiante->nombre} {$estudiante->apellido}");
        $horaCorta = substr($llegadaTarde->hora, 0, 5);
        $fecha = $llegadaTarde->fecha;

        $avisoLimite = $limiteAlcanzado
            ? "\n\nEste estudiante ya acumula {$totalEnPeriodo} llegadas tarde en el período académico actual."
            : '';

        if ($estudiante->correo && $estudiante->estado === 'activo') {
            $this->mailService->sendGeneric(
                $estudiante->correo,
                'Llegada tarde registrada',
                "Hola {$nombreCompleto},\n\nSe registró tu llegada tarde el {$fecha} a las {$horaCorta}." . $avisoLimite
            );
        }

        $correosAcudientes = Usuario::whereIn(
            'id_user',
            EstudiantesPadre::where('id_estudiante', $llegadaTarde->id_alumno)->where('activo', 1)->pluck('id_acudiente')
        )
            ->where('estado', 'activo')
            ->whereNotNull('correo')
            ->pluck('correo')
            ->filter()
            ->all();

        if (!empty($correosAcudientes)) {
            $this->mailService->sendGeneric(
                $correosAcudientes,
                'Llegada tarde registrada',
                "Se registró una llegada tarde del estudiante {$nombreCompleto} el {$fecha} a las {$horaCorta}." . $avisoLimite
            );
        }

        if ($limiteAlcanzado) {
            $correosDirectivoDocente = Usuario::where('perfil', self::PERFIL_DIRECTIVO_DOCENTE)
                ->where('estado', 'activo')
                ->whereNotNull('correo')
                ->pluck('correo')
                ->filter()
                ->all();

            $this->mailService->sendGeneric(
                array_values(array_unique(array_merge($this->mailToVicerrectoria, $correosDirectivoDocente))),
                'Falta por llegadas tarde acumuladas',
                "El estudiante {$nombreCompleto} (documento {$estudiante->documento}) acumula {$totalEnPeriodo} llegadas tarde en el período académico actual. Última llegada tarde: {$fecha} a las {$horaCorta}."
            );
        }
    }
}

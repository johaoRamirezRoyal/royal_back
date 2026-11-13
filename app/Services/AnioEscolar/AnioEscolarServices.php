<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\ConfiguracionAcademica;
use App\Models\AnioEscolar\Periodo;
use App\Models\AnioEscolar\PeriodoAcademico;
use App\Services\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnioEscolarServices extends Service
{
    // Fila única de configuración (ver migración create_configuracion_academica_table).
    private const ID_CONFIG = 1;

    public function obtenerAniosEscolares()
    {
        try {
            $anios = Anio::all();

            return [
                'error' => false,
                'message' => 'Años escolares obtenidos exitosamente',
                'data' => $anios,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener años escolares');

            return [
                'error' => true,
                'message' => 'Error al obtener años escolares: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function obtenerUltimoAnioEscolar()
    {
        try {
            // Prioriza el año marcado como habilitado (activo=1) en la BD — es la fuente de
            // verdad real: la mantiene al día el cron diario (cerrarYAbrirAnioEscolar) pero
            // también puede overridearla un admin a mano desde "Años escolares" (habilitar/
            // deshabilitar). Solo si no hay ninguno habilitado (antes de la primera corrida
            // del cron, o si se deshabilitaron todos) se recurre al cálculo por calendario, y
            // en último caso al año más reciente que exista.
            $anioVigente = Anio::where('activo', 1)->latest('id')->first();

            if (! $anioVigente) {
                $tipo = $this->obtenerTipoCalendario();
                $anioInicioObjetivo = $this->anioInicioParaFecha(Carbon::now(), $tipo);

                $anioVigente = ($anioInicioObjetivo !== null ? Anio::where('anio_inicio', $anioInicioObjetivo)->first() : null)
                    ?? Anio::latest('id')->first();
            }

            // fecha_inicio/fecha_fin no persisten en la tabla `anios` — se calculan al vuelo
            // a partir del calendario configurado, igual que rangoDeAnioEscolar(), para que
            // el frontend (Home, "año escolar transcurrido") no tenga que reimplementar las
            // reglas de calendario A/B ni pegarle a un segundo endpoint gateado por la opción 99.
            if ($anioVigente) {
                $tipo = $this->obtenerTipoCalendario();
                $rango = $this->rangoParaAnioInicio((int) $anioVigente->anio_inicio, $tipo);
                $anioVigente->fecha_inicio = $rango['fecha_min'];
                $anioVigente->fecha_fin = $rango['fecha_max'];
            }

            return [
                'error' => false,
                'message' => 'Último año escolar obtenido exitosamente',
                'data' => $anioVigente,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener el último año escolar');

            return [
                'error' => true,
                'message' => 'Error al obtener el último año escolar: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Resuelve el año escolar al que pertenece una fecha dada, según el calendario
     * (A o B) configurado en `configuracion_academica`.
     */
    public function obtenerAnioEscolarPorFecha(string $fecha): ?Anio
    {
        $tipo = $this->obtenerTipoCalendario();
        $anioInicio = $this->anioInicioParaFecha(Carbon::parse($fecha), $tipo);

        if ($anioInicio === null) {
            return null;
        }

        return Anio::where('anio_inicio', $anioInicio)->first();
    }

    public function obtenerConfiguracionCalendario(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Configuración de calendario obtenida correctamente',
                'data' => ConfiguracionAcademica::findOrFail(self::ID_CONFIG),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener la configuración de calendario académico');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la configuración de calendario académico',
                'data' => null,
            ];
        }
    }

    public function actualizarConfiguracionCalendario(string $tipoCalendario): array
    {
        try {
            if (! in_array($tipoCalendario, ['A', 'B'], true)) {
                return [
                    'error' => true,
                    'message' => 'El tipo de calendario debe ser A o B',
                    'data' => null,
                ];
            }

            $config = ConfiguracionAcademica::findOrFail(self::ID_CONFIG);
            $config->update(['tipo_calendario' => $tipoCalendario]);

            return [
                'error' => false,
                'message' => 'Configuración de calendario actualizada correctamente',
                'data' => $config,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al actualizar la configuración de calendario académico');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la configuración de calendario académico',
                'data' => null,
            ];
        }
    }

    /**
     * Crea manualmente un año escolar a partir de su año de inicio (respaldo si el cron
     * `anio-escolar:cerrar-abrir` no corrió, o para precrear años futuros). No cierra
     * ningún otro año escolar — a diferencia de cerrarYAbrirAnioEscolar(), esto es
     * puramente aditivo. Solo nace activo si su anio_inicio es el que corresponde a hoy
     * según el calendario; un año futuro precreado (el caso típico de este endpoint) nace
     * inactivo para no competir con el año realmente vigente en obtenerUltimoAnioEscolar()
     * (que toma el activo más reciente por id).
     */
    public function crearAnioEscolarManual(int $anioInicio): array
    {
        try {
            if (Anio::where('anio_inicio', $anioInicio)->exists()) {
                return [
                    'error' => true,
                    'message' => "Ya existe un año escolar registrado para {$anioInicio}",
                    'data' => null,
                ];
            }

            $tipo = $this->obtenerTipoCalendario();
            $rango = $this->rangoParaAnioInicio($anioInicio, $tipo);
            $anioInicioEsperado = $this->anioInicioParaFecha(Carbon::now(), $tipo);

            $activo = $anioInicio === $anioInicioEsperado;
            $anio = Anio::create([
                'anio_inicio' => $anioInicio,
                'anio_fin' => $rango['anio_fin'],
                'activo' => $activo,
                'fechareg' => Carbon::now(),
            ]);

            // Solo un año habilitado: si nace habilitado, apaga los demás.
            if ($activo) {
                $this->deshabilitarOtros($anio->id);
            }

            return [
                'error' => false,
                'message' => 'Año escolar creado exitosamente',
                'data' => $anio,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al crear el año escolar');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el año escolar',
                'data' => null,
            ];
        }
    }

    /**
     * Habilita/deshabilita manualmente un año escolar puntual (independiente del cron
     * automático de cierre/apertura). Solo puede haber un año habilitado: habilitar uno
     * deshabilita los demás.
     */
    public function actualizarEstadoAnioEscolar(int $id, bool $activo): array
    {
        try {
            $anio = Anio::findOrFail($id);

            $otros = 0;
            if ($activo) {
                $otros = $this->deshabilitarOtros($anio->id);
            }
            $anio->update(['activo' => $activo]);

            return [
                'error' => false,
                'message' => $activo
                    ? ($otros ? 'Año escolar habilitado; los demás quedaron deshabilitados' : 'Año escolar habilitado')
                    : 'Año escolar deshabilitado',
                'data' => $anio,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al actualizar el estado del año escolar');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el estado del año escolar',
                'data' => null,
            ];
        }
    }

    /**
     * Entry point del comando programado `anio-escolar:cerrar-abrir`: cierra el/los año(s)
     * escolar(es) activos que ya no correspondan a hoy, y abre (crea y activa) el que sí
     * corresponde si todavía no existe. Si el año que ya existe fue desactivado a mano, no
     * se reactiva solo — se respeta el estado manual.
     */
    public function cerrarYAbrirAnioEscolar(): array
    {
        try {
            $tipo = $this->obtenerTipoCalendario();
            $anioInicioEsperado = $this->anioInicioParaFecha(Carbon::now(), $tipo);

            $cerrados = [];
            $activos = Anio::where('activo', 1)->get();
            foreach ($activos as $anio) {
                if ($anio->anio_inicio !== $anioInicioEsperado) {
                    $anio->update(['activo' => false]);
                    $cerrados[] = $anio->id;
                }
            }

            $abierto = null;
            if ($anioInicioEsperado !== null && ! Anio::where('anio_inicio', $anioInicioEsperado)->exists()) {
                $rango = $this->rangoParaAnioInicio($anioInicioEsperado, $tipo);
                $nuevo = Anio::create([
                    'anio_inicio' => $anioInicioEsperado,
                    'anio_fin' => $rango['anio_fin'],
                    'activo' => true,
                    'fechareg' => Carbon::now(),
                ]);
                $abierto = $nuevo->id;
            }

            $mensaje = 'Sin cambios: el año escolar vigente ya está correcto';
            if ($cerrados || $abierto) {
                $mensaje = sprintf(
                    'Cerrados: %d año(s) [%s]. Abierto: %s',
                    count($cerrados),
                    implode(', ', $cerrados),
                    $abierto ?? 'ninguno (fuera de rango de calendario)'
                );
            }

            return [
                'error' => false,
                'message' => $mensaje,
                'data' => ['cerrados' => $cerrados, 'abierto' => $abierto],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al cerrar/abrir el año escolar');

            return [
                'error' => true,
                'message' => 'Error en el servidor al cerrar/abrir el año escolar: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * "Iniciar siguiente año escolar" en un clic (módulo Año Escolar y Periodos): toma el
     * año habilitado (o el más reciente), crea el siguiente si no existe y lo deja como el
     * único habilitado. Con `$copiarPeriodos`, si el año nuevo no tiene periodos
     * administrativos, copia los del año anterior con las fechas corridas un año. Por
     * último marca en curso el primer periodo del año nuevo, si tiene.
     *
     * @return array{error: bool, message: string, data: ?array{anio: Anio, periodos_copiados: int}}
     */
    public function iniciarSiguienteAnio(bool $copiarPeriodos, PeriodoServices $periodoServices): array
    {
        try {
            $actual = Anio::where('activo', 1)->latest('id')->first() ?? Anio::orderByDesc('anio_inicio')->first();

            if (! $actual) {
                return ['error' => true, 'message' => 'No hay ningún año escolar registrado para tomar como base', 'data' => null];
            }

            $anioInicio = (int) $actual->anio_inicio + 1;

            $resultado = DB::transaction(function () use ($actual, $anioInicio, $copiarPeriodos, $periodoServices) {
                $siguiente = Anio::where('anio_inicio', $anioInicio)->first() ?? Anio::create([
                    'anio_inicio' => $anioInicio,
                    'anio_fin' => $this->rangoParaAnioInicio($anioInicio, $this->obtenerTipoCalendario())['anio_fin'],
                    'activo' => true,
                    'fechareg' => Carbon::now(),
                ]);
                $siguiente->update(['activo' => true]);
                $this->deshabilitarOtros($siguiente->id);

                $copiados = 0;
                if ($copiarPeriodos && ! Periodo::where('id_anio', $siguiente->id)->exists()) {
                    $copiados = $periodoServices->copiarDeAnio($actual->id, $siguiente->id);
                }

                $primero = Periodo::where('id_anio', $siguiente->id)->where('activo', 1)->orderBy('fecha_inicio')->first();
                if ($primero) {
                    Periodo::where('en_curso', 1)->update(['en_curso' => false]);
                    $primero->update(['en_curso' => true]);
                }

                return ['anio' => $siguiente->fresh(), 'periodos_copiados' => $copiados];
            });

            return [
                'error' => false,
                'message' => "Año escolar {$resultado['anio']->anio_inicio}–{$resultado['anio']->anio_fin} iniciado"
                    .($resultado['periodos_copiados'] ? "; se copiaron {$resultado['periodos_copiados']} periodos" : ''),
                'data' => $resultado,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al iniciar el siguiente año escolar');

            return [
                'error' => true,
                'message' => 'Error en el servidor al iniciar el siguiente año escolar',
                'data' => null,
            ];
        }
    }

    /**
     * Regla: solo un año escolar habilitado. Deshabilita todos menos `$idHabilitado` y
     * devuelve cuántos apagó. Lo usan crear, habilitar y el cron de cierre/apertura.
     */
    private function deshabilitarOtros(int $idHabilitado): int
    {
        return Anio::where('activo', 1)->where('id', '!=', $idHabilitado)->update(['activo' => false]);
    }

    /**
     * Cierre de año: el último año escolar que ya terminó y el que le sigue, mientras ese
     * siguiente no haya empezado su primer periodo académico. Fuera de esa ventana, null.
     *
     * Un año "termina" con el fin de su último periodo académico; sin periodos, con el fin de
     * su rango de calendario. La ventana se cierra cuando arranca el primer periodo del año
     * siguiente: si nunca se le definen periodos, sigue abierta (y el aviso tiene sentido).
     *
     * @return array{anio_cerrado: Anio, anio_siguiente: ?Anio, periodos_siguiente: int}|null
     */
    public function estadoCierreAnio(): ?array
    {
        $hoy = Carbon::today()->toDateString();
        $tipo = $this->obtenerTipoCalendario();

        $fin = fn (Anio $a): string => substr((string) (PeriodoAcademico::where('id_anio_escolar', $a->id)->max('fecha_fin')
            ?? $this->rangoParaAnioInicio((int) $a->anio_inicio, $tipo)['fecha_max']), 0, 10);

        $cerrado = Anio::orderByDesc('anio_inicio')->get()->first(fn (Anio $a) => $fin($a) < $hoy);

        if (! $cerrado) {
            return null;
        }

        $siguiente = Anio::where('anio_inicio', (int) $cerrado->anio_inicio + 1)->first();
        $periodos = $siguiente ? PeriodoAcademico::where('id_anio_escolar', $siguiente->id)->get(['fecha_inicio']) : collect();
        $inicioSiguiente = $periodos->min('fecha_inicio');

        if ($inicioSiguiente && substr((string) $inicioSiguiente, 0, 10) <= $hoy) {
            return null;
        }

        return [
            'anio_cerrado' => $cerrado,
            'anio_siguiente' => $siguiente,
            'periodos_siguiente' => $periodos->count(),
        ];
    }

    /**
     * Rango de fechas válido para un año escolar ya existente (usado por
     * PeriodoAcademicoRequest para validar fecha_inicio/fecha_fin de un período).
     */
    public function rangoDeAnioEscolar(Anio $anio): array
    {
        $tipo = $this->obtenerTipoCalendario();

        return $this->rangoParaAnioInicio((int) $anio->anio_inicio, $tipo);
    }

    public function obtenerTipoCalendario(): string
    {
        return ConfiguracionAcademica::find(self::ID_CONFIG)->tipo_calendario ?? 'B';
    }

    /**
     * Dado el año de inicio y el tipo de calendario, calcula el año de fin y el rango de
     * fechas válido para ese año escolar.
     *   - B (agosto-junio): cruza dos años calendario, anio_fin = anio_inicio + 1.
     *   - A (febrero-noviembre): coincide con el año calendario, anio_fin = anio_inicio.
     */
    private function rangoParaAnioInicio(int $anioInicio, string $tipoCalendario): array
    {
        if ($tipoCalendario === 'A') {
            return [
                'anio_fin' => $anioInicio,
                'fecha_min' => "{$anioInicio}-02-01",
                'fecha_max' => "{$anioInicio}-11-30",
            ];
        }

        $anioFin = $anioInicio + 1;

        return [
            'anio_fin' => $anioFin,
            'fecha_min' => "{$anioInicio}-08-01",
            'fecha_max' => "{$anioFin}-06-30",
        ];
    }

    /**
     * Dada una fecha y el tipo de calendario, resuelve a qué anio_inicio pertenece.
     * Calendario A tiene un hueco intencional en diciembre/enero (fuera de cualquier año
     * escolar) — devuelve null en ese caso.
     */
    private function anioInicioParaFecha(Carbon $fecha, string $tipoCalendario): ?int
    {
        if ($tipoCalendario === 'A') {
            return ($fecha->month >= 2 && $fecha->month <= 11) ? $fecha->year : null;
        }

        return $fecha->month >= 8 ? $fecha->year : $fecha->year - 1;
    }
}

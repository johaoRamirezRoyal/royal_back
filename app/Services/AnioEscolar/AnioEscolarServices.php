<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\ConfiguracionAcademica;
use App\Services\Service;
use Carbon\Carbon;

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

            if (!$anioVigente) {
                $tipo = $this->obtenerTipoCalendario();
                $anioInicioObjetivo = $this->anioInicioParaFecha(Carbon::now(), $tipo);

                $anioVigente = ($anioInicioObjetivo !== null ? Anio::where('anio_inicio', $anioInicioObjetivo)->first() : null)
                    ?? Anio::latest('id')->first();
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
            if (!in_array($tipoCalendario, ['A', 'B'], true)) {
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

            $anio = Anio::create([
                'anio_inicio' => $anioInicio,
                'anio_fin' => $rango['anio_fin'],
                'activo' => $anioInicio === $anioInicioEsperado,
                'fechareg' => Carbon::now(),
            ]);

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
     * automático de cierre/apertura).
     */
    public function actualizarEstadoAnioEscolar(int $id, bool $activo): array
    {
        try {
            $anio = Anio::findOrFail($id);
            $anio->update(['activo' => $activo]);

            return [
                'error' => false,
                'message' => $activo ? 'Año escolar habilitado' : 'Año escolar deshabilitado',
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
            if ($anioInicioEsperado !== null && !Anio::where('anio_inicio', $anioInicioEsperado)->exists()) {
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

<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Periodo;
use App\Services\Service;
use Exception;

/**
 * Catálogo de periodos institucionales (tabla `periodos`, con año escolar y las banderas
 * `activo`/`en_curso`) — concepto de año académico, no de un módulo en particular. Vive
 * aquí (no en EvaluacionesServices) porque el módulo de Evaluaciones es solo uno de sus
 * consumidores; cualquier otro módulo que necesite el catálogo o el periodo en curso debe
 * inyectar este servicio en vez de duplicar la consulta o pasar por Evaluaciones.
 *
 * Nota: `Periodo` (tabla `periodos`) es un modelo distinto de `PeriodoAcademico` (tabla
 * `periodo_academico`, ver PeriodoAcademicoServices) — dos catálogos de periodo legacy
 * separados que conviven en el sistema, no lo mismo con otro nombre.
 */
class PeriodoServices extends Service
{
    public function listar(array $filtros = []): array
    {
        try {
            $query = Periodo::with('anioEscolar')->orderByDesc('id_anio')->orderByDesc('numero');

            if (array_key_exists('activo', $filtros) && $filtros['activo'] !== '') {
                $query->where('activo', (bool) $filtros['activo']);
            }

            return [
                'error' => false,
                'message' => 'ok',
                'data' => $query->get(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los periodos');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los periodos',
                'data' => [],
            ];
        }
    }

    /**
     * Periodo institucional activo (tabla `periodos`, NO `periodo_academico` — esa es
     * solo para lo académico). `en_curso` es explícito y no se deriva de nada más (ni de
     * `periodos.activo` ni del año escolar activo): en la práctica puede haber varios años
     * escolares y periodos con `activo=1` a la vez, lo que hacía imposible resolver de
     * forma confiable "cuál es el periodo vigente ahora" — de ahí la columna dedicada. No
     * hay CRUD para `periodos` todavía, se marca a mano.
     *
     * Método público (no privado) porque otros servicios (ej. EvaluacionesServices) lo
     * necesitan para resolver el periodo vigente sin reimplementar esta consulta — inyecta
     * este servicio en vez de duplicarla.
     */
    public function resolverActivo(): ?Periodo
    {
        return Periodo::where('en_curso', 1)->latest('id')->first();
    }

    /** Misma resolución que `resolverActivo()`, en el shape de respuesta de la API — usado por GET /evaluaciones/periodo-activo. */
    public function periodoActivo(): array
    {
        try {
            $periodo = $this->resolverActivo();

            return [
                'error' => false,
                'message' => 'ok',
                'data' => $periodo?->load('anioEscolar'),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el periodo activo');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el periodo activo',
                'data' => null,
            ];
        }
    }
}

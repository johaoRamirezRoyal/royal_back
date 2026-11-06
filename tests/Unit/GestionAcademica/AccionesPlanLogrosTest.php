<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Quién puede qué sobre los logros según el estado del plan (NotasService::acciones). Los roles
 * llegan como los arma NotasService::rol: quien aprueba (151) también gestiona.
 */
class AccionesPlanLogrosTest extends TestCase
{
    private const DOCENTE = ['docente' => true, 'gestiona' => false, 'aprueba' => false];
    private const GESTOR = ['docente' => false, 'gestiona' => true, 'aprueba' => false];
    private const APROBADOR = ['docente' => false, 'gestiona' => true, 'aprueba' => true];
    private const AJENO = ['docente' => false, 'gestiona' => false, 'aprueba' => false];

    /** Las acciones en true, en orden: editar, enviar, aprobar, devolver, reabrir. */
    private function puede(string $estado, array $rol, bool $abierto = true): array
    {
        return array_keys(array_filter(NotasService::acciones($estado, $rol, $abierto)));
    }

    public function test_reglas(): void
    {
        $casos = [
            ['BORRADOR', self::DOCENTE, true, ['editar', 'enviar']],
            ['BORRADOR', self::GESTOR, true, ['editar', 'enviar']],
            ['DEVUELTO', self::DOCENTE, true, ['editar', 'enviar']],
            ['BORRADOR', self::AJENO, true, []],
            ['EN_REVISION', self::DOCENTE, true, []],              // en revisión el docente no edita
            ['EN_REVISION', self::GESTOR, true, []],               // ni quien solo gestiona
            ['EN_REVISION', self::APROBADOR, true, ['editar', 'aprobar', 'devolver']],
            ['APROBADO', self::DOCENTE, true, []],                 // aprobado: nadie edita
            ['APROBADO', self::APROBADOR, true, ['reabrir']],
            ['BORRADOR', self::DOCENTE, false, []],                // fuera de plazo no se edita ni envía
            ['APROBADO', self::APROBADOR, false, []],              // ni se reabre
            ['EN_REVISION', self::APROBADOR, false, ['aprobar', 'devolver']], // aprobar sí, aun vencido
        ];

        foreach ($casos as [$estado, $rol, $abierto, $esperado]) {
            $this->assertSame($esperado, $this->puede($estado, $rol, $abierto), "$estado abierto=" . var_export($abierto, true) . ' rol=' . json_encode($rol));
        }
    }
}

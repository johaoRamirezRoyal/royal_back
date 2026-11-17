<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Quién puede qué sobre los referentes según el estado del plan (NotasService::acciones). Los
 * roles llegan como los arma NotasService::rol: quien aprueba (154) también gestiona. El docente
 * envía (y retira) por asignatura: `enviar` y `retirar` son ids de asignatura.
 */
class AccionesPlanReferentesTest extends TestCase
{
    // Docente con dos asignaturas en el grado (5 y 9).
    private const DOCENTE = ['docente' => true, 'asignaturas' => [5, 9], 'gestiona' => false, 'aprueba' => false];
    private const GESTOR = ['docente' => false, 'asignaturas' => [], 'gestiona' => true, 'aprueba' => false];
    private const APROBADOR = ['docente' => false, 'asignaturas' => [], 'gestiona' => true, 'aprueba' => true];
    private const AJENO = ['docente' => false, 'asignaturas' => [], 'gestiona' => false, 'aprueba' => false];

    /** Las acciones disponibles: las de sí/no en orden y, aparte, enviar y retirar con sus asignaturas. */
    private function puede(string $estado, array $rol, bool $abierto = true, array $enviadas = []): array
    {
        $a = NotasService::acciones($estado, $rol, $abierto, $enviadas);

        return [
            array_keys(array_filter(array_diff_key($a, ['enviar' => 0, 'retirar' => 0]))),
            $a['enviar'],
            $a['retirar'],
        ];
    }

    public function test_reglas(): void
    {
        $casos = [
            // [estado, rol, abierto, enviadas, [sí/no], enviar, retirar]
            ['BORRADOR', self::DOCENTE, true, [], ['editar'], [5, 9], []],      // envía cada asignatura suya
            ['BORRADOR', self::DOCENTE, true, [5, 7], ['editar'], [9], [5]],   // la 5 ya la envió: puede retirarla
            ['DEVUELTO', self::DOCENTE, true, [], ['editar'], [5, 9], []],
            ['BORRADOR', self::GESTOR, true, [], ['editar'], [], []],          // coordinación no envía
            ['BORRADOR', self::APROBADOR, true, [], ['editar'], [], []],
            ['BORRADOR', self::AJENO, true, [], [], [], []],
            ['EN_REVISION', self::DOCENTE, true, [5, 9], [], [], []],          // en revisión no edita ni retira
            ['EN_REVISION', self::GESTOR, true, [], [], [], []],
            ['EN_REVISION', self::APROBADOR, true, [], ['editar', 'aprobar', 'devolver'], [], []],
            ['APROBADO', self::DOCENTE, true, [], [], [], []],                 // aprobado: nadie edita
            ['APROBADO', self::APROBADOR, true, [], ['reabrir'], [], []],
            ['BORRADOR', self::DOCENTE, false, [], [], [], []],                // fuera de plazo no se edita ni se envía
            ['BORRADOR', self::GESTOR, false, [], [], [], []],
            ['APROBADO', self::APROBADOR, false, [], [], [], []],              // ni se reabre
            ['EN_REVISION', self::APROBADOR, false, [], ['aprobar', 'devolver'], [], []], // aprobar sí, aun vencido
        ];

        foreach ($casos as [$estado, $rol, $abierto, $enviadas, $sino, $enviar, $retirar]) {
            $this->assertSame([$sino, $enviar, $retirar], $this->puede($estado, $rol, $abierto, $enviadas), "$estado abierto=" . var_export($abierto, true) . ' enviadas=' . json_encode($enviadas) . ' rol=' . json_encode($rol));
        }
    }
}

<?php

namespace Tests\Unit\ProcesoCompra;

use App\Http\Requests\ProcesoCompra\VerificarEntregaRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Coherencia entre la decisión (cerrar/devolución) y los rubros de la verificación de entrega.
 */
class VerificarEntregaRequestTest extends TestCase
{
    /** @param array<string, string|null> $datos */
    private function errores(array $datos): array
    {
        $request = VerificarEntregaRequest::create('/', 'POST', $datos);
        $validator = Validator::make($request->all(), $request->rules());
        $validator->after($request->after());

        return $validator->errors()->get('decision');
    }

    private function rubros(string $plazos = 'Si', ?string $obs = null): array
    {
        return ['cantidad' => 'Si', 'calidad' => 'Si', 'precios' => 'Si', 'plazos' => $plazos, 'observacion_plazo' => $obs];
    }

    public function test_cerrar_con_todo_cumple_es_valido(): void
    {
        $this->assertSame([], $this->errores([...$this->rubros(), 'decision' => 'cerrar']));
    }

    public function test_no_se_puede_cerrar_si_un_rubro_no_cumple(): void
    {
        $this->assertNotEmpty($this->errores([...$this->rubros('No', 'Llegó tarde'), 'decision' => 'cerrar']));
    }

    public function test_devolucion_exige_motivo(): void
    {
        $this->assertNotEmpty($this->errores([...$this->rubros('No'), 'decision' => 'devolucion']));
        $this->assertSame([], $this->errores([...$this->rubros('No', 'Llegó tarde'), 'decision' => 'devolucion']));
    }
}

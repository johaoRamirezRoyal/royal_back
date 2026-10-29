<?php

namespace Tests\Unit;

use App\Http\Controllers\Admissions\AdmissionsController;
use App\Models\Usuarios\Usuario;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * El acudiente de una petición de admisiones sale de la sesión, no del cuerpo: antes
 * cualquiera podía crear o listar inscripciones de otro acudiente mandando su id.
 */
class AdmisionesAcudienteSesionTest extends TestCase
{
    private function acudiente(array $claims, ?int $usuarioSesion, array $cuerpo, string $campo): ?int
    {
        $payload = Mockery::mock();
        $payload->shouldReceive('get')->andReturnUsing(fn ($k) => $claims[$k] ?? null);
        // Un mock nuevo por llamada: con shouldReceive sobre la fachada, Mockery seguía
        // devolviendo el primer token del test.
        $jwt = Mockery::mock();
        $jwt->shouldReceive('parseToken')->andReturnSelf();
        $jwt->shouldReceive('getPayload')->andReturn($payload);
        JWTAuth::swap($jwt);

        $request = Request::create('/api/admisiones/inscripcion', 'POST', $cuerpo);
        $request->setUserResolver(fn () => $usuarioSesion ? tap(new Usuario(), fn ($u) => $u->id_user = $usuarioSesion) : null);

        $controller = (new ReflectionClass(AdmissionsController::class))->newInstanceWithoutConstructor();

        return (new ReflectionMethod($controller, 'acudienteDeLaSesion'))->invoke($controller, $request, $campo);
    }

    public function test_un_acudiente_no_puede_actuar_a_nombre_de_otro(): void
    {
        $this->assertSame(7, $this->acudiente(['system' => 'admissions'], 7, ['id_usuario_registro' => 99], 'id_usuario_registro'));
        $this->assertSame(7, $this->acudiente(['system' => 'admissions'], 7, ['id_acudiente' => 99], 'id_acudiente'));
    }

    public function test_el_modo_admin_usa_el_acudiente_firmado_en_el_token(): void
    {
        $claims = ['source' => 'token_exchange', 'acudiente_id' => 42];
        $this->assertSame(42, $this->acudiente($claims, 1, ['id_acudiente' => 99], 'id_acudiente'));
    }

    public function test_el_modo_admin_sin_acudiente_en_el_token_usa_el_del_cuerpo(): void
    {
        $this->assertSame(55, $this->acudiente(['source' => 'token_exchange'], 1, ['id_acudiente' => 55], 'id_acudiente'));
    }

    public function test_sin_sesion_no_hay_acudiente(): void
    {
        $this->assertNull($this->acudiente(['system' => 'admissions'], null, ['id_usuario_registro' => 99], 'id_usuario_registro'));
    }
}

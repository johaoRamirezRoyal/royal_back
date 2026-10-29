<?php

namespace Tests\Unit;

use App\Http\Middleware\JwtFromCookie;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * /api/compartido usa la cookie del sistema de la página que llama (header X-Sistema):
 * con una sesión de acudiente abierta en el mismo navegador, Reportes de admisiones (personal)
 * hablaba como ese acudiente y no podía cambiar el estado.
 */
class JwtFromCookieCompartidoTest extends TestCase
{
    private function token(array $cookies, ?string $sistema): ?string
    {
        $request = Request::create('/api/compartido/inscripcion/estado', 'PUT', [], $cookies);
        if ($sistema) $request->headers->set('X-Sistema', $sistema);

        return (new JwtFromCookie())->tokenCompartido($request);
    }

    public function test_cada_sistema_usa_su_cookie(): void
    {
        $ambas = ['token' => 'personal', 'admissions_token' => 'acudiente'];

        $this->assertSame('personal', $this->token($ambas, 'general'));
        $this->assertSame('acudiente', $this->token($ambas, 'admissions'));
    }

    public function test_sin_header_o_sin_esa_cookie_se_usa_la_otra(): void
    {
        $this->assertSame('acudiente', $this->token(['token' => 'personal', 'admissions_token' => 'acudiente'], null));
        $this->assertSame('acudiente', $this->token(['admissions_token' => 'acudiente'], 'general'));
        $this->assertSame('personal', $this->token(['token' => 'personal'], 'admissions'));
    }
}

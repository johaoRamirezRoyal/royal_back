<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** LimitarIntentosFallidos: cuenta solo los fallos (401/422) y un acierto reinicia la cuenta. */
class LimitarIntentosFallidosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La "contraseña" correcta es "bien"; cualquier otra responde 401, como el login.
        Route::post('/prueba-intentos', fn () => request('pass') === 'bien'
            ? response()->json(['ok' => true])
            : response()->json(['error' => true], 401))->middleware('intentos:2,1');
    }

    public function test_bloquea_tras_los_fallos_permitidos(): void
    {
        $this->postJson('/prueba-intentos', ['user' => 'a@x.co', 'pass' => 'mal'])->assertStatus(401);
        $this->postJson('/prueba-intentos', ['user' => 'a@x.co', 'pass' => 'mal'])->assertStatus(401);
        // Bloqueado aunque ahora la contraseña sea la correcta.
        $this->postJson('/prueba-intentos', ['user' => 'a@x.co', 'pass' => 'bien'])->assertStatus(429);
        // Otra cuenta desde la misma IP no queda bloqueada.
        $this->postJson('/prueba-intentos', ['user' => 'b@x.co', 'pass' => 'bien'])->assertOk();
    }

    public function test_un_acierto_reinicia_la_cuenta(): void
    {
        $this->postJson('/prueba-intentos', ['user' => 'c@x.co', 'pass' => 'mal'])->assertStatus(401);
        $this->postJson('/prueba-intentos', ['user' => 'c@x.co', 'pass' => 'bien'])->assertOk();
        $this->postJson('/prueba-intentos', ['user' => 'c@x.co', 'pass' => 'mal'])->assertStatus(401);
        $this->postJson('/prueba-intentos', ['user' => 'c@x.co', 'pass' => 'bien'])->assertOk();
    }
}

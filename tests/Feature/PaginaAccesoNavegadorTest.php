<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Una ruta privada abierta en el navegador muestra la página de acceso; la app (apiFetch, o
 * fetch sin cabeceras) sigue recibiendo el JSON de siempre.
 */
class PaginaAccesoNavegadorTest extends TestCase
{
    private const NAVEGADOR = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

    public function test_sin_sesion_el_navegador_ve_la_pagina(): void
    {
        $this->get('/api/usuarios/mis-permisos', ['Accept' => self::NAVEGADOR])
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Esta dirección es privada')
            ->assertSee(config('app.frontend_url'));
    }

    public function test_sin_sesion_la_app_recibe_json(): void
    {
        $this->getJson('/api/usuarios/mis-permisos')
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado']);

        // fetch() sin cabeceras (descargas de PDF, por ejemplo) manda */*: tampoco es HTML.
        $this->get('/api/usuarios/mis-permisos', ['Accept' => '*/*'])
            ->assertStatus(401)
            ->assertJson(['message' => 'No autenticado']);
    }

    public function test_sin_permiso_el_navegador_ve_la_pagina(): void
    {
        Route::middleware('api')->get('/api/_prueba-403', fn () => response()->json(['error' => true], 403));

        $this->get('/api/_prueba-403', ['Accept' => self::NAVEGADOR])
            ->assertStatus(403)
            ->assertSee('No tienes acceso a este recurso');
    }

    public function test_ruta_inexistente_en_el_navegador_ve_la_pagina_y_la_app_no(): void
    {
        $this->get('/api/no-existe', ['Accept' => self::NAVEGADOR])
            ->assertStatus(404)
            ->assertSee('No encontramos lo que buscas');

        $this->getJson('/api/no-existe')->assertStatus(404)->assertDontSee('No encontramos');
    }

    public function test_mantenimiento_en_el_navegador_ve_la_pagina_y_la_app_recibe_json(): void
    {
        config(['app.mantenimiento' => true]);

        $this->get('/api/usuarios/mis-permisos', ['Accept' => self::NAVEGADOR])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300')
            ->assertSee('OMNIA está en mantenimiento')
            ->assertSee('/api/usuarios/mis-permisos'); // "Volver a intentar" recarga la misma dirección

        $this->getJson('/api/usuarios/mis-permisos')
            ->assertStatus(503)
            ->assertJson(['mantenimiento' => true]);
    }
}

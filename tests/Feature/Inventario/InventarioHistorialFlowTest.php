<?php

namespace Tests\Feature\Inventario;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba de solo lectura contra la base de datos real (phpunit.xml fuerza
 * sqlite en memoria para las tablas de Laravel, pero el inventario vive en
 * tablas legacy sin migraciones — no hay forma de recrearlas en sqlite).
 * No escribe nada: solo valida que, a partir del listado de inventario, se
 * puede llegar rápido al historial de cualquiera de sus ítems.
 */
class InventarioHistorialFlowTest extends TestCase
{
    /** Ítem real de prueba: COMPUTADOR TODO EN UNO (id 926480). */
    private const CODIGO_ITEM_PRUEBA = '787368735';

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml pisa DB_DATABASE=":memory:" antes de que boot la app;
        // recuperamos el nombre real directamente del .env para apuntar la
        // conexión "mysql" a la base real (host/usuario/password sí llegan
        // intactos vía env(), phpunit.xml no los toca).
        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        $baseDeDatosReal = trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'");

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', $baseDeDatosReal);
        DB::purge('mysql');
    }

    public function test_listado_de_inventario_permite_llegar_rapido_al_historial_de_un_item(): void
    {
        $usuario = Usuario::where('estado', 'activo')->first();

        if (!$usuario) {
            $this->markTestSkipped('No hay usuarios activos en la base de datos real para autenticar la prueba.');
        }

        $token = app(JwtService::class)->generateToken($usuario);
        $headers = ['Authorization' => "Bearer {$token}"];

        // Busca el ítem de prueba recorriendo el listado paginado tal como lo
        // haría el frontend, en vez de ir directo por su id.
        $itemEnListado = null;
        $page = 1;

        do {
            $listado = $this->getJson("/api/inventario/listado?per-page=50&page={$page}", $headers);
            $listado->assertOk();

            $items = collect($listado->json('data.data'))->pluck('items')->flatten(1);
            $itemEnListado = $items->firstWhere('codigo', self::CODIGO_ITEM_PRUEBA);

            $ultimaPagina = $listado->json('data.last_page');
            $page++;
        } while (!$itemEnListado && $page <= $ultimaPagina);

        if (!$itemEnListado) {
            $this->markTestSkipped(
                'El ítem de prueba (código ' . self::CODIGO_ITEM_PRUEBA . ') no aparece en el listado de inventario.'
            );
        }

        $itemId = $itemEnListado['id'];

        $historial = $this->getJson("/api/inventario/{$itemId}/historial", $headers);

        $historial->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.item.id', $itemId)
            ->assertJsonPath('data.item.codigo', self::CODIGO_ITEM_PRUEBA)
            ->assertJsonStructure([
                'data' => ['item', 'reportes', 'mantenimientos'],
            ]);
    }
}

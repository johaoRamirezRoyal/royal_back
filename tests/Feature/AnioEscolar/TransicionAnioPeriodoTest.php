<?php

namespace Tests\Feature\AnioEscolar;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\Periodo;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\AnioEscolar\PeriodoServices;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contra la base real (ver tests/Feature/Inventario): todo corre dentro de una transacción
 * que se revierte al final — `anio_escolar` y `periodos` son InnoDB.
 */
class TransicionAnioPeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_iniciar_siguiente_anio_copia_periodos_y_avanzar_pasa_al_segundo(): void
    {
        $base = (int) Anio::max('anio_inicio') + 50;
        Anio::query()->update(['activo' => false]);
        $anio = Anio::create(['anio_inicio' => $base, 'anio_fin' => $base + 1, 'activo' => true]);
        foreach ([['1', "{$base}-08-01", "{$base}-11-30"], ['2', "{$base}-12-01", ($base + 1).'-06-30']] as [$numero, $inicio, $fin]) {
            Periodo::create(['numero' => $numero, 'id_anio' => $anio->id, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'activo' => true, 'en_curso' => false]);
        }

        $periodos = app(PeriodoServices::class);
        $resultado = app(AnioEscolarServices::class)->iniciarSiguienteAnio(true, $periodos);

        $this->assertFalse($resultado['error'], $resultado['message']);
        $nuevo = $resultado['data']['anio'];
        $this->assertSame($base + 1, (int) $nuevo->anio_inicio);
        $this->assertSame(1, Anio::where('activo', 1)->count());
        $this->assertSame(2, $resultado['data']['periodos_copiados']);

        $copias = Periodo::where('id_anio', $nuevo->id)->orderBy('fecha_inicio')->get();
        $this->assertSame(($base + 1).'-08-01', $copias[0]->fecha_inicio->toDateString());
        $this->assertSame(1, Periodo::where('en_curso', 1)->count());
        $this->assertTrue($copias[0]->fresh()->en_curso);

        $avance = $periodos->avanzar();
        $this->assertFalse($avance['error'], $avance['message']);
        $this->assertSame($copias[1]->id, $periodos->resolverActivo()->id);

        $this->assertTrue($periodos->avanzar()['error'], 'En el último periodo no hay siguiente');
    }
}

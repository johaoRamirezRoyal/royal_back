<?php

namespace Tests\Unit\Reservas;

use App\Models\Reservas\ConfiguracionReservas;
use App\Services\MailService;
use App\Services\Prestamos\PrestamosService;
use App\Services\Reservas\ReservasServices;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cubre validarFechaReserva() en solitario (sin BD): crearReserva() en sí necesita
 * fixtures de salón/usuario contra la BD real (las tablas `salones`/`usuarios` no tienen
 * migración en este repo, ver AGENTS.md — no existen en la BD sqlite de test), así que la
 * regla de fechas se aisló a un método puro justamente para poder probarla acá.
 */
class ReservasServicesTest extends TestCase
{
    private function config(int $min, int $max): ConfiguracionReservas
    {
        return new ConfiguracionReservas([
            'dias_min_anticipacion' => $min,
            'dias_max_anticipacion' => $max,
        ]);
    }

    private function validar(Carbon $fechaReserva, Carbon $hoy, Carbon $ahora, ConfiguracionReservas $config): ?string
    {
        $service = new ReservasServices(
            $this->createMock(PrestamosService::class),
            $this->createMock(MailService::class),
        );
        $method = new ReflectionMethod(ReservasServices::class, 'validarFechaReserva');
        $method->setAccessible(true);

        return $method->invoke($service, $fechaReserva, $hoy, $ahora, $config);
    }

    public function test_rechaza_fecha_pasada(): void
    {
        $hoy = Carbon::parse('2026-06-10');

        $error = $this->validar(Carbon::parse('2026-06-09'), $hoy, $hoy->copy()->setTime(8, 0), $this->config(1, 1));

        $this->assertNotNull($error);
    }

    public function test_rechaza_el_mismo_dia_aunque_min_sea_0(): void
    {
        $hoy = Carbon::parse('2026-06-10');

        // dias_min_anticipacion=0 no debería poder configurarse (validado en el
        // controller con min:1), pero el propio método igual bloquea "hoy" como
        // segunda barrera — no depende únicamente de esa validación externa.
        $error = $this->validar($hoy->copy(), $hoy, $hoy->copy()->setTime(8, 0), $this->config(0, 0));

        $this->assertNotNull($error);
        $this->assertStringContainsString('día de hoy', $error);
    }

    public function test_permite_dentro_de_la_ventana_por_defecto_antes_del_mediodia(): void
    {
        $hoy = Carbon::parse('2026-06-10');
        $manana = $hoy->copy()->addDay();

        $this->assertNull($this->validar($manana, $hoy, $hoy->copy()->setTime(11, 59), $this->config(1, 1)));
    }

    public function test_despues_del_mediodia_la_ventana_por_defecto_se_corre_un_dia(): void
    {
        $hoy = Carbon::parse('2026-06-10');
        $manana = $hoy->copy()->addDay();
        $pasadoManana = $hoy->copy()->addDays(2);

        $error = $this->validar($manana, $hoy, $hoy->copy()->setTime(12, 0), $this->config(1, 1));
        $this->assertNotNull($error);

        $this->assertNull($this->validar($pasadoManana, $hoy, $hoy->copy()->setTime(12, 0), $this->config(1, 1)));
    }

    public function test_ventana_configurable_permite_fin_de_semana_como_dia_calendario(): void
    {
        // Un miércoles con ventana min=2/max=3 permite viernes o sábado (fines de semana
        // cuentan como días normales, no se saltan).
        $hoy = Carbon::parse('2026-06-10'); // miércoles
        $viernes = $hoy->copy()->addDays(2);
        $sabado = $hoy->copy()->addDays(3);
        $domingo = $hoy->copy()->addDays(4);

        $this->assertNull($this->validar($viernes, $hoy, $hoy->copy()->setTime(9, 0), $this->config(2, 3)));
        $this->assertNull($this->validar($sabado, $hoy, $hoy->copy()->setTime(9, 0), $this->config(2, 3)));
        $this->assertNotNull($this->validar($domingo, $hoy, $hoy->copy()->setTime(9, 0), $this->config(2, 3)));
    }

    public function test_rechaza_fecha_antes_de_la_ventana_minima(): void
    {
        $hoy = Carbon::parse('2026-06-10');
        $config = $this->config(3, 5);

        $error = $this->validar($hoy->copy()->addDays(2), $hoy, $hoy->copy()->setTime(9, 0), $config);

        $this->assertNotNull($error);
        $this->assertStringContainsString('anticipación', $error);
    }

    public function test_rechaza_fecha_mas_alla_de_la_ventana_maxima(): void
    {
        $hoy = Carbon::parse('2026-06-10');
        $config = $this->config(1, 5);

        $error = $this->validar($hoy->copy()->addDays(6), $hoy, $hoy->copy()->setTime(9, 0), $config);

        $this->assertNotNull($error);
        $this->assertStringContainsString('más de', $error);
    }
}

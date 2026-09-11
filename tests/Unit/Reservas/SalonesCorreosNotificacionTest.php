<?php

namespace Tests\Unit\Reservas;

use App\Models\Reservas\Salones;
use Tests\TestCase;

/** Salones::correosNotificacion() es una lectura pura de un atributo — no requiere BD (la tabla `salones` no tiene migración en este repo, ver ReservasServicesTest). */
class SalonesCorreosNotificacionTest extends TestCase
{
    public function test_sin_correo_configurado_devuelve_vacio(): void
    {
        $salon = new Salones(['correo_notificacion' => null]);

        $this->assertSame([], $salon->correosNotificacion());
    }

    public function test_parsea_varios_correos_separados_por_coma(): void
    {
        $salon = new Salones(['correo_notificacion' => ' Rector@Colegio.edu.co ,coordinador@colegio.edu.co']);

        $this->assertSame(['rector@colegio.edu.co', 'coordinador@colegio.edu.co'], $salon->correosNotificacion());
    }
}

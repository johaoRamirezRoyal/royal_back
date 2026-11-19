<?php

namespace Tests\Unit\AdminManagement;

use App\Services\AdminManagement\BasesDatosService;
use Tests\TestCase;

/** Correo institucional → base de su colegio (login con Google) y dominios que aceptan los formularios de usuarios. */
class DominiosInstitucionalesTest extends TestCase
{
    public function test_cada_dominio_va_a_la_base_de_su_colegio(): void
    {
        $this->assertSame('mysql', BasesDatosService::connectionDeCorreo('ana@royalschool.edu.co'));
        $this->assertSame('sami_play', BasesDatosService::connectionDeCorreo('Ana@PlayAndLearn.edu.co'));
        $this->assertSame('sami_hebreo', BasesDatosService::connectionDeCorreo('ana@colegiohebreounion.edu.co'));
        $this->assertNull(BasesDatosService::connectionDeCorreo('ana@hebreounion.edu.co'));
        $this->assertNull(BasesDatosService::connectionDeCorreo('ana@gmail.com'));
        $this->assertNull(BasesDatosService::connectionDeCorreo('sin-arroba'));
    }

    public function test_los_formularios_aceptan_los_dominios_del_colegio_de_la_sesion(): void
    {
        config(['database.default' => 'sami_hebreo']);
        $this->assertSame('@colegiohebreounion.edu.co', BasesDatosService::arrobasDeLaSesion());

        config(['database.default' => 'mysql']);
        $this->assertSame('@royalschool.edu.co', BasesDatosService::arrobasDeLaSesion());
    }
}

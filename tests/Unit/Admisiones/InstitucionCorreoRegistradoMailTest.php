<?php

namespace Tests\Unit\Admisiones;

use Tests\TestCase;

/** Plantillas de correo de Admisiones, renderizadas sin BD. */
class InstitucionCorreoRegistradoMailTest extends TestCase
{
    public function test_confirma_el_correo_registrado_de_la_institucion(): void
    {
        $html = view('emails.institucionCorreoRegistrado', [
            'nombreInstitucion' => 'Jardín Pequeños Exploradores',
            'email' => 'coordinacion@exploradores.edu.co',
            'fecha' => '29/10/2026',
        ])->render();

        $this->assertStringContainsString('Jardín Pequeños Exploradores', $html);
        $this->assertStringContainsString('coordinacion<wbr>@exploradores.edu.co', $html);
        $this->assertStringContainsString('Registrado el 29/10/2026', $html);
        $this->assertStringContainsString('Instituciones asociadas — Admisiones', $html);
    }

    public function test_los_demas_correos_conservan_el_departamento_por_defecto(): void
    {
        $html = view('emails.registroAcudiente', ['nombre' => 'Ana', 'email' => 'ana@correo.com', 'url' => null])->render();

        $this->assertStringContainsString('Departamento de Admisiones', $html);
        $this->assertStringNotContainsString('Instituciones asociadas', $html);
    }
}

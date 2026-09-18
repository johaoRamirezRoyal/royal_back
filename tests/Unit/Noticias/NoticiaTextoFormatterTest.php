<?php

namespace Tests\Unit\Noticias;

use App\Support\NoticiaTextoFormatter;
use Tests\TestCase;

class NoticiaTextoFormatterTest extends TestCase
{
    public function test_convierte_negrita_cursiva_y_color(): void
    {
        $html = NoticiaTextoFormatter::aHtml("**hola** _mundo_ {#2563eb:azul}");

        $this->assertSame('<strong>hola</strong> <em>mundo</em> <span style="color:#2563eb">azul</span>', $html);
    }

    public function test_escapa_html_arbitrario_del_usuario(): void
    {
        $html = NoticiaTextoFormatter::aHtml('<script>alert(1)</script>');

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_preserva_saltos_de_linea(): void
    {
        $html = NoticiaTextoFormatter::aHtml("linea 1\nlinea 2");

        $this->assertSame("linea 1<br />\nlinea 2", $html);
    }

    public function test_nulo_y_vacio_devuelven_cadena_vacia(): void
    {
        $this->assertSame('', NoticiaTextoFormatter::aHtml(null));
        $this->assertSame('', NoticiaTextoFormatter::aHtml(''));
    }
}

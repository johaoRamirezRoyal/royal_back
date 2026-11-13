<?php

namespace Tests\Unit\Inventario;

use App\Pdf\Inventario\ReporteOperativoPdfService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * En producción (PHP detrás del servidor web) existe $_SERVER['DOCUMENT_ROOT'] y TCPDF se lo
 * antepone a todo `<img src="/...">`: la ruta temporal (/tmp/...) deja de existir y el PDF
 * sale sin logo ni firma, sin error. Por eso las imágenes van embebidas (`src="@base64"`).
 * Solo reproduce la regresión en Linux: en Windows el temporal es `C:\...` y TCPDF no lo toca.
 */
class ReporteOperativoPdfImagenesTest extends TestCase
{
    public function test_el_logo_y_la_firma_de_la_directora_salen_con_document_root_definido(): void
    {
        $im = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        ob_start();
        imagejpeg($im);
        $jpg = ob_get_clean();

        Http::fake([
            '*logotipoBackground.png' => Http::response($png),
            '*firma_administrativa.jpg' => Http::response($jpg),
        ]);
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/html/public';

        try {
            $pdf = app(ReporteOperativoPdfService::class)->generate([
                'usuario' => 'Ana', 'area' => 'Sistemas', 'fecha' => '2026-11-06', 'hora' => '08:00',
                'fecha_dia' => 'viernes', 'id' => 1, 'descripcion' => 'Daño', 'marca' => 'HP',
                'estado' => 'Reportado', 'observacion' => '', 'firma_reporta' => null,
                'visto_bueno' => true, 'solucion' => null,
            ]);
        } finally {
            $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
        }

        $this->assertSame(2, substr_count($pdf, '/Subtype /Image'));
    }
}

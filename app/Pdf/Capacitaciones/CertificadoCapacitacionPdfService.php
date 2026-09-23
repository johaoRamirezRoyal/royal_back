<?php

namespace App\Pdf\Capacitaciones;

use TCPDF;

/**
 * Certificado de capacitación — réplica del legado `sami_royal/vistas/modulos/imprimir/
 * capacitaciones/certificado.php` (FPDF): misma hoja 500x360 mm horizontal, mismo fondo,
 * textos, fuentes y colores. Posicionamiento absoluto con Cell/MultiCell, no writeHTML.
 */
class CertificadoCapacitacionPdfService
{
    public function generate(string $nombreUsuario, string $nombreCurso, string $descripcionCurso, string $fecha, string $codigo): string
    {
        $pdf = new class('L', 'mm', [500, 360], true, 'UTF-8', false) extends TCPDF {
            public function Header() {}
            public function Footer() {}
        };

        $pdf->SetTitle('Certificado de capacitación');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->Image(resource_path('pdf/capacitaciones/fondo_certificado.jpg'), 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight());

        $pdf->SetY(50);
        $pdf->SetFont('times', 'B', 38);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 80, 'CERTIFICADO DE CAPACITACIÓN', 0, 1, 'C');

        $pdf->SetFont('times', '', 28);
        $pdf->SetTextColor(127, 140, 141);
        $pdf->Cell(0, 2, 'Se hace constar que', 0, 2, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('times', 'B', 34);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 15, $nombreUsuario, 0, 2, 'C');

        $pdf->SetFont('times', '', 26);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 10, 'Ha completado satisfactoriamente el curso de capacitación en:', 0, 'C');

        $pdf->Ln(5);
        $pdf->SetFont('times', 'B', 25);
        $pdf->SetTextColor(22, 160, 133);
        $pdf->Cell(0, 12, $nombreCurso, 0, 1, 'C');
        $pdf->Ln(5);

        $anchoContenido = 220;
        $pdf->SetX(($pdf->getPageWidth() - $anchoContenido) / 2);
        $pdf->MultiCell($anchoContenido, 10, "\n" . $descripcionCurso, 0, 'C');

        $pdf->Ln(10);
        $pdf->SetFont('times', 'I', 16);
        $pdf->Cell(0, 10, 'Fecha de finalización de la capacitación: ' . $fecha, 0, 1, 'C');

        $pdf->SetY(-30);
        $pdf->SetFont('times', '', 15);
        $pdf->SetTextColor(127, 140, 141);
        $pdf->Cell(0, 10, 'Código de verificación único: ' . $codigo, 0, 0, 'C');

        return $pdf->Output('', 'S');
    }
}

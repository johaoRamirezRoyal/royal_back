<?php

namespace App\Pdf\Inventario;

use App\Services\Cloudinary\CloudinaryService;
use TCPDF;

/**
 * Reporte operativo de un inventario reportado (PDF vertical) — recreación del legacy
 * (vistas/modulos/imprimir/solucion.php) con TCPDF y HTML, misma técnica que HojaVidaPdfService.
 */
class ReporteOperativoPdfService
{
    /**
     * @param  array{usuario: string, area: string, fecha: string, hora: string, fecha_dia: string, id: int|string, descripcion: string, marca: string, estado: string, observacion: string, firma_reporta: ?string, visto_bueno: bool, solucion: ?array{responsable: string, fecha: string, descripcion: string, firma: ?string}}  $data
     */
    public function generate(array $data): string
    {
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF
        {
            public function Header() {}

            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(127, 127, 127);
                $this->SetFont($this->getFontFamily(), 'I', 10);
                $this->Cell(0, 10, 'Pagina '.$this->PageNo(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Reporte solucion');
        $pdf->SetSubject('Reporte solucion');
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $cloudinary = app(CloudinaryService::class);

        // Logo institucional fijo (no el de la marca por dominio) — ver AGENTS.md "Logo en PDFs".
        $logoPath = $cloudinary->rutaLocalRoyalSchool('logotipoBackground.png');
        $logoCell = $logoPath && is_file($logoPath) ? '<img src="'.$logoPath.'" width="120">' : '';

        // V°B°: firma fija de la directora administrativa, como en el legacy (img/firma_administrativa.jpg).
        $firmaDirectoraPath = $data['visto_bueno'] ? $cloudinary->rutaLocalRoyalSchool('firma_administrativa.jpg') : null;
        $firmaDirectora = $firmaDirectoraPath && is_file($firmaDirectoraPath)
            ? '<img src="'.$firmaDirectoraPath.'" height="35">'
            : '';

        $solucion = $data['solucion'] ?? null;
        $firmaReporta = $this->firmaOTexto($data['firma_reporta'], $data['usuario'], 35);
        $firmaSoluciona = $solucion ? $this->firmaOTexto($solucion['firma'], $solucion['responsable'], 35) : '';
        $firmaSolucionaPie = $solucion ? $this->firmaOTexto($solucion['firma'], $solucion['responsable'], 22) : '';
        $firmaReportaPie = $solucion ? $this->firmaOTexto($data['firma_reporta'], $data['usuario'], 22) : '';
        $fechaSolucion = $solucion ? e($solucion['fecha']) : '';

        $html = '
        <table border="1" cellpadding="4" style="width:100%; text-align:center;">
            <tr>
                <td style="width:32%;">'.$logoCell.'</td>
                <td style="width:46%; font-size:10px; font-weight:bold;"><br>PROCESO DE MANTENIMIENTO<br><br>REPORTE OPERATIVO</td>
                <td style="width:22%; font-size:10px; font-weight:bold;">VERSIÓN 01<br>03-06-2025<br>1-1</td>
            </tr>
        </table>
        <br><br>
        <table cellpadding="2" style="width:100%; font-size:9px;">
            <tr>
                <td style="width:5%;"></td>
                <td style="width:33%;"><b>Nombre:</b> '.e($data['usuario']).'</td>
                <td style="width:30%;"><b>Area:</b> '.e($data['area']).'</td>
                <td style="width:32%; text-align:right;"><b>Fecha:</b> '.e($data['fecha']).'</td>
            </tr>
        </table>
        <br><br>
        <table border="1" cellpadding="2" style="width:100%; font-size:8px; text-align:center;">
            <tr style="font-weight:bold;">
                <th style="width:10%;">ID</th><th style="width:43%;">DESCRIPCION</th><th style="width:20%;">MARCA</th><th style="width:27%;">ESTADO</th>
            </tr>
            <tr>
                <td>'.e((string) $data['id']).'</td><td>'.e($data['descripcion']).'</td><td>'.e($data['marca']).'</td><td>'.e($data['estado']).'</td>
            </tr>
            <tr><td></td><td></td><td></td><td></td></tr>
            <tr><td></td><td></td><td></td><td></td></tr>
        </table>
        <br><br>
        <table cellpadding="2" style="width:100%; font-size:9px; text-align:center;">
            <tr>
                <td style="width:5%;"></td>
                <td style="width:30%; height:42px;">'.$firmaReporta.'</td>
                <td style="width:30%; height:42px;">'.$firmaDirectora.'</td>
                <td style="width:30%; height:42px;">'.$firmaSoluciona.'</td>
            </tr>
            <tr>
                <td></td>
                <td>_________________________<br><b>Reporte Realizado Por</b></td>
                <td>_________________________<br><b>V°B° Directora Administrativa</b></td>
                <td>_________________________<br><b>Reporte Remitido a</b></td>
            </tr>
        </table>
        <br><br>
        <table cellpadding="2" style="width:100%; font-size:9px; text-align:center;">
            <tr><td><b>Observación:</b> '.e($data['observacion']).'</td></tr>
            <tr><td><b>Solución:</b> '.e($solucion['descripcion'] ?? '').'</td></tr>
        </table>
        <br><br><br>
        <table cellpadding="3" style="width:100%; font-size:8px;">
            <tr>
                <td style="width:62%;"><b>Solicitud Recibida Por:</b> ________'.$firmaSolucionaPie.'________________</td>
                <td style="width:19%;"><b>Hora:</b> ___<u>'.e($data['hora']).'</u>___</td>
                <td style="width:19%;"><b>Fecha:</b> __<u>'.e($data['fecha_dia']).'</u>__</td>
            </tr>
            <tr>
                <td colspan="2"><b>Solucionado Por:</b> ________'.$firmaSolucionaPie.'________________</td>
                <td><b>Fecha:</b> __<u>'.$fechaSolucion.'</u>__</td>
            </tr>
            <tr>
                <td colspan="2"><b>Recibido Conforme Por:</b> ________'.$firmaReportaPie.'________________</td>
                <td><b>Fecha:</b> __<u>'.$fechaSolucion.'</u>__</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * Imagen de la firma registrada o, si el usuario no tiene, su nombre subrayado (como el legacy).
     */
    private function firmaOTexto(?string $firma, string $nombre, int $alto): string
    {
        return $firma
            ? '<img src="@'.base64_encode($firma).'" height="'.$alto.'">'
            : '<u>'.e($nombre).'</u>';
    }
}

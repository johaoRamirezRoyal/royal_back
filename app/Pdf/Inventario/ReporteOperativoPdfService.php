<?php

namespace App\Pdf\Inventario;

use TCPDF;

/**
 * Reporte operativo de un inventario reportado (PDF vertical) — recreación del legacy
 * (vistas/modulos/imprimir/reporte.php) con TCPDF y HTML, misma técnica que HojaVidaPdfService.
 */
class ReporteOperativoPdfService
{
    /**
     * @param array{usuario: string, area: string, fecha: string, id: int|string, descripcion: string, marca: string, estado: string, observacion: string, solucion: ?array} $data
     */
    public function generate(array $data): string
    {
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
            public function Header() {}
            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(127, 127, 127);
                $this->SetFont($this->getFontFamily(), 'I', 10);
                $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Reporte');
        $pdf->SetSubject('Reporte');
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Logo institucional fijo (no el de la marca por dominio) — ver AGENTS.md "Logo en PDFs".
        $logoPath = public_path('storage/royal-school/logotipoBackground.png');
        $logoCell = is_file($logoPath) ? '<img src="' . $logoPath . '" width="120">' : '';

        $html = '
        <table border="1" cellpadding="4" style="width:100%; text-align:center;">
            <tr>
                <td style="width:30%;">' . $logoCell . '</td>
                <td style="width:48%; font-size:11px; font-weight:bold;"><br><br>REPORTE OPERATIVO<br>PROCESO DE MANTENIMIENTO</td>
                <td style="width:22%; font-size:9px; font-weight:bold;"><br>VERSIÓN 01<br>03-06-2025<br>1-1</td>
            </tr>
        </table>
        <br><br>
        <table cellpadding="2" style="width:100%; font-size:10px;">
            <tr>
                <td style="width:40%;"><b>Nombre:</b> ' . e($data['usuario']) . '</td>
                <td style="width:35%;"><b>Área:</b> ' . e($data['area']) . '</td>
                <td style="width:25%;"><b>Fecha:</b> ' . e($data['fecha']) . '</td>
            </tr>
        </table>
        <br><br>
        <table border="1" cellpadding="3" style="width:100%; font-size:9px; text-align:center;">
            <tr style="font-weight:bold;">
                <th style="width:10%;">ID</th><th style="width:34%;">DESCRIPCIÓN</th><th style="width:20%;">MARCA</th>
                <th style="width:10%;">CANTIDAD</th><th style="width:26%;">ESTADO</th>
            </tr>
            <tr>
                <td>' . e((string) $data['id']) . '</td><td>' . e($data['descripcion']) . '</td><td>' . e($data['marca']) . '</td>
                <td>1</td><td>' . e($data['estado']) . '</td>
            </tr>
            <tr><td></td><td></td><td></td><td></td><td></td></tr>
            <tr><td></td><td></td><td></td><td></td><td></td></tr>
        </table>
        <br><br><br>
        <table style="width:100%; font-size:8px; text-align:center;">
            <tr>
                <td style="width:33%;">' . e($data['usuario']) . '<br>__________________________</td>
                <td style="width:34%;"><br>__________________________</td>
                <td style="width:33%;"><br>__________________________</td>
            </tr>
            <tr style="font-weight:bold;">
                <td>Reporte Realizado Por</td><td>V°B° Directora Administrativa</td><td>Reporte Remitido A</td>
            </tr>
        </table>
        <br><br>
        <p style="font-size:9px;"><b>Observación:</b> ' . e($data['observacion']) . '</p>
        <br>
        <table cellpadding="3" style="width:100%; font-size:9px;">
            <tr>
                <td style="width:60%;"><b>Solicitud Recibida Por:</b> ______________________________</td>
                <td style="width:20%;"><b>Hora:</b> __________</td>
                <td style="width:20%;"><b>Fecha:</b> __________</td>
            </tr>
            ' . $this->filaSolucion($data['solucion'] ?? null) . '
            <tr>
                <td colspan="2"><b>Recibido Conforme Por:</b> ________________________________________________</td>
                <td><b>Fecha:</b> __________</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * Fila "Solucionado Por": en blanco para llenar a mano si el reporte sigue pendiente; con
     * responsable, fecha, tiempo de respuesta, descripción y firma si ya se solucionó.
     *
     * @param array{responsable: string, fecha: string, tiempo: string, descripcion: string, firma: ?string}|null $solucion
     */
    private function filaSolucion(?array $solucion): string
    {
        if (!$solucion) {
            return '<tr>
                <td colspan="2"><b>Solucionado Por:</b> ______________________________________________________</td>
                <td><b>Fecha:</b> __________</td>
            </tr>';
        }

        $firma = $solucion['firma']
            ? '<img src="@' . base64_encode($solucion['firma']) . '" height="40"><br>'
            : '<br><br>';

        return '<tr>
                <td colspan="2"><b>Solucionado Por:</b> ' . e($solucion['responsable']) . '</td>
                <td><b>Fecha:</b> ' . e($solucion['fecha']) . '</td>
            </tr>
            <tr><td colspan="3"><b>Solución:</b> ' . e($solucion['descripcion']) . '</td></tr>
            <tr><td colspan="3"><b>Tiempo de respuesta:</b> ' . e($solucion['tiempo']) . '</td></tr>
            <tr>
                <td colspan="2" style="text-align:center;">' . $firma . '__________________________<br><b>Firma de quien solucionó</b></td>
                <td></td>
            </tr>';
    }
}

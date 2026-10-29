<?php

namespace App\Pdf\Areas;

use TCPDF;

/**
 * Checklist de Historial de Checks (Áreas Comunes) — un PDF por consulta filtrada, con
 * TODOS los ítems de Área Común que matcheen bloque/área (no solo los que ya tienen
 * check, a diferencia de la tabla de la página), agrupados por Bloque → Área y, dentro de
 * cada área, por descripción + estado (check / mantenimiento / reporte / sin check), con
 * su "Cantidad" — ver InventarioServices::generarHistorialChecksPdf, que arma y agrupa los
 * datos. Mismo estilo que los PDF de inventario (HojaVidaPdfService): encabezado con borde
 * de tres celdas, banda azul `#dce6f1` y títulos `#003366`.
 */
class HistorialChecksPdfService
{
    private const ESTADOS = [
        'check' => ['label' => 'Check confirmado', 'color' => '#16a34a'],
        'mantenimiento' => ['label' => 'Mantenimiento programado', 'color' => '#d97706'],
        'reporte' => ['label' => 'Reporte pendiente', 'color' => '#dc2626'],
        'sin_check' => ['label' => 'Sin check', 'color' => '#6b7280'],
    ];

    /**
     * @param array{
     *   logo_path?: string|null,
     *   anio_label: string,
     *   periodo_label: string,
     *   generado_por: string,
     *   bloques: array<string, array<string, array<int, array{
     *     descripcion: string,
     *     cantidad: int,
     *     estado: string,
     *     detalle: array<int, string>,
     *   }>>>,
     * } $data
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
                $this->SetFont($this->getFontFamily(), 'I', 9);
                $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Historial de Checks - Areas Comunes');
        $pdf->SetSubject('Historial de Checks');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $logoPath = $data['logo_path'] ?? null;
        $logoCell = $logoPath && is_file($logoPath) ? '<img src="'.$logoPath.'" width="110">' : '';

        $encabezado = '
        <table border="1" cellpadding="6" style="width:100%; text-align:center;">
            <tr>
                <td style="width:24%;">'.$logoCell.'</td>
                <td style="width:52%; font-size:12px; font-weight:bold;"><br>PROCESO DE MANTENIMIENTO<br>HISTORIAL DE CHECKS<br>ÁREAS COMUNES</td>
                <td style="width:24%; font-size:9px;"><br><b>'.e($data['anio_label']).'</b><br>'.e($data['periodo_label']).'<br>Emisión: '.now()->format('d/m/Y').'</td>
            </tr>
        </table>
        <table border="1" cellpadding="4" style="width:100%; font-size:9px; background-color:#dce6f1;">
            <tr>
                <td style="width:60%;"><b>Generado por:</b> '.e($data['generado_por']).'</td>
                <td style="width:40%; text-align:right;"><b>Fecha y hora:</b> '.now()->format('d/m/Y H:i').'</td>
            </tr>
        </table>';
        $pdf->writeHTML($encabezado, true, false, true, false, '');
        $pdf->Ln(2);

        if (empty($data['bloques'])) {
            $pdf->writeHTML('<p style="text-align:center; color:#888888;">No hay áreas comunes para estos filtros</p>', true, false, true, false, '');
        }

        foreach ($data['bloques'] as $nombreBloque => $areas) {
            $tabla = '<table border="1" cellpadding="4" style="width:100%; font-size:9px;">
                <tr style="background-color:#003366; color:#ffffff; font-weight:bold;">
                    <td colspan="4" style="font-size:10px;">BLOQUE: '.e(mb_strtoupper($nombreBloque)).'</td>
                </tr>
                <tr style="background-color:#dce6f1; font-weight:bold; text-align:center;">
                    <td style="width:34%; text-align:left;">Ítem</td>
                    <td style="width:8%;">Cant.</td>
                    <td style="width:20%;">Estado</td>
                    <td style="width:38%; text-align:left;">Detalle</td>
                </tr>';

            foreach ($areas as $nombreArea => $items) {
                $tabla .= '<tr style="background-color:#eef1f5; color:#003366; font-weight:bold;">
                    <td colspan="4">'.e($nombreArea).'</td>
                </tr>';

                foreach ($items as $i => $item) {
                    $estilo = self::ESTADOS[$item['estado']] ?? self::ESTADOS['sin_check'];
                    $fondo = $i % 2 ? '#f5f5f5' : '#ffffff';
                    // Se escapa cada parte por separado: escapar el texto ya unido con
                    // `&middot;` imprimía la entidad literal en el PDF.
                    $detalle = $item['detalle']
                        ? implode(' &middot; ', array_map('e', $item['detalle']))
                        : '&mdash;';

                    $tabla .= '<tr style="background-color:'.$fondo.';">
                        <td style="width:34%;">'.e($item['descripcion']).'</td>
                        <td style="width:8%; text-align:center; font-weight:bold;">'.(int) $item['cantidad'].'</td>
                        <td style="width:20%; text-align:center; color:'.$estilo['color'].'; font-weight:bold;">'.$estilo['label'].'</td>
                        <td style="width:38%; color:#333333;">'.$detalle.'</td>
                    </tr>';
                }
            }

            $tabla .= '</table>';

            $pdf->writeHTML($tabla, true, false, true, false, '');
            $pdf->Ln(3);
        }

        return $pdf->Output('', 'S');
    }
}

<?php

namespace App\Pdf\Areas;

use TCPDF;

/**
 * Checklist de Historial de Checks (Áreas Comunes) — un PDF por consulta filtrada, con
 * TODOS los ítems de Área Común que matcheen bloque/área (no solo los que ya tienen
 * check, a diferencia de la tabla de la página), agrupados por Bloque → Área y, dentro de
 * cada área, por descripción + estado del check (una fila por "Lámparas" con su
 * "Cantidad", no una por cada unidad física — ver
 * InventarioServices::generarHistorialChecksPdf, que arma y agrupa los datos). Cada fila
 * marca "Check"/"Sin Check", con fecha/periodo/año del check dentro de la misma celda
 * cuando aplica, y por separado — independiente del check — "Programación"/"Fecha De
 * Programación" con el mantenimiento preventivo pendiente del grupo, si tiene uno.
 */
class HistorialChecksPdfService
{
    private const CHECK_ESTILO = [
        'si' => ['label' => 'Check', 'color' => '#16a34a'],
        'no' => ['label' => 'Sin Check', 'color' => '#dc2626'],
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
     *     check: string,
     *     check_fecha: string|null,
     *     check_periodo: string|null,
     *     check_anio: string|null,
     *     programacion: string|null,
     *     fecha_programacion: string|null,
     *   }>>>,
     * } $data
     */
    public function generate(array $data): string
    {
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
            public function Header() {}
            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(127, 127, 127);
                $this->SetFont($this->getFontFamily(), 'I', 9);
                $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
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
        $logoCell = $logoPath && is_file($logoPath)
            ? '<img src="' . $logoPath . '" width="100">'
            : '';

        $encabezado = '
        <table style="width:100%;" cellpadding="4">
            <tr>
                <td style="width:25%; text-align:center; vertical-align:middle;">' . $logoCell . '</td>
                <td style="width:75%; vertical-align:middle;">
                    <p style="font-size:15px; font-weight:bold; margin:0;">Historial de Checks</p>
                    <p style="font-size:11px; margin:2px 0;">' . e($data['anio_label']) . ' &middot; ' . e($data['periodo_label']) . '</p>
                    <p style="font-size:9px; color:#555555; margin:2px 0;">Documento generado por: ' . e($data['generado_por']) . '</p>
                    <p style="font-size:8px; color:#888888; margin:2px 0;">' . now()->format('d/m/Y H:i') . '</p>
                </td>
            </tr>
        </table>
        <hr>';

        $pdf->writeHTMLCell(0, 0, '', '', $encabezado, 0, 1, false, true, 'L', true);
        $pdf->Ln(4);

        if (empty($data['bloques'])) {
            $pdf->writeHTML('<p style="text-align:center; color:#888888;">No hay areas comunes para estos filtros</p>', true, false, true, false, '');
        }

        foreach ($data['bloques'] as $nombreBloque => $areas) {
            $tabla = '<table cellpadding="3" border="1" style="width:100%; font-size:9px;">
                <tr style="background-color:#0b1f5e; color:#ffffff; font-weight:bold;">
                    <td colspan="5">' . e($nombreBloque) . '</td>
                </tr>
                <tr style="background-color:#f5f5f5; font-weight:bold; text-align:center;">
                    <td style="width:30%; text-align:left;">Item</td>
                    <td style="width:8%;">Cantidad</td>
                    <td style="width:22%;">Check</td>
                    <td style="width:25%;">Programacion</td>
                    <td style="width:15%;">Fecha De Programacion</td>
                </tr>';

            foreach ($areas as $nombreArea => $items) {
                $tabla .= '<tr style="background-color:#eef1fa; font-weight:bold;">
                    <td colspan="5">' . e($nombreArea) . '</td>
                </tr>';

                foreach ($items as $item) {
                    $checkEstilo = self::CHECK_ESTILO[$item['check']] ?? self::CHECK_ESTILO['no'];
                    $checkCell = '<span style="color:' . $checkEstilo['color'] . '; font-weight:bold;">' . $checkEstilo['label'] . '</span>';

                    if ($item['check'] === 'si') {
                        $detalle = array_filter([$item['check_fecha'] ?? null, $item['check_periodo'] ?? null, $item['check_anio'] ?? null]);
                        if (!empty($detalle)) {
                            $checkCell .= '<br><span style="font-size:7px; color:#555555;">' . e(implode(' &middot; ', $detalle)) . '</span>';
                        }
                    }

                    $programacion = $item['programacion'] ? e($item['programacion']) : '&mdash;';
                    $fechaProgramacion = $item['fecha_programacion'] ? e($item['fecha_programacion']) : '&mdash;';

                    $tabla .= '<tr>
                        <td style="width:30%;">' . e($item['descripcion']) . '</td>
                        <td style="width:8%; text-align:center;">' . (int) $item['cantidad'] . '</td>
                        <td style="width:22%; text-align:center;">' . $checkCell . '</td>
                        <td style="width:25%;">' . $programacion . '</td>
                        <td style="width:15%; text-align:center;">' . $fechaProgramacion . '</td>
                    </tr>';
                }
            }

            $tabla .= '</table>';

            $pdf->writeHTML($tabla, true, false, true, false, '');
            $pdf->Ln(4);
        }

        return $pdf->Output('', 'S');
    }
}

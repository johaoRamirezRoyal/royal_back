<?php

namespace App\Pdf\Inventario;

use TCPDF;

/**
 * Checklist de mantenimiento preventivo (PDF) — recreación del legacy
 * (vistas/modulos/imprimir/mantenimientosSistemas/mantenimientosEquipos.php) con
 * TCPDF, misma técnica basada en HTML (writeHTMLCell/writeHTML) que usaba el original,
 * en vez del posicionamiento absoluto de otros PDFs de este proyecto (no hay un diseño
 * pixel-perfect que replicar acá, el legacy ya era una tabla HTML simple).
 *
 * Funciona igual para Sistemas y Operativos: la única variación real es el checklist de
 * columnas, que depende de si el equipo es de la categoría "Computadores" (con checks de
 * SO/antivirus/backup) o cualquier otra (checklist genérico de revisión + limpieza) — el
 * legacy solo distinguía por eso, no por sistemas/operativos en sí.
 */
class MantenimientoChecklistPdfService
{
    /**
     * @param array{
     *   logo_path?: string|null,
     *   tipo_categoria_label: string,
     *   categoria_nombre: string,
     *   es_computadores: bool,
     *   responsable_nombre: string,
     *   con_solucion: bool,
     *   equipos: array<int, array{id: int|string, descripcion: string, area: string}>,
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
                $this->SetFont($this->getFontFamily(), 'I', 10);
                $this->Cell(0, 10, 'Pagina ' . $this->getAliasNumPage(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Mantenimiento preventivo');
        $pdf->SetSubject('Mantenimiento preventivo');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $logoPath = $data['logo_path'] ?? null;
        $logoCell = $logoPath && is_file($logoPath)
            ? '<img src="' . $logoPath . '" width="120">'
            : '';

        $tipoLabel = strtoupper($data['tipo_categoria_label']);

        $encabezado = '
        <table style="width:100%;" border="1" cellpadding="3">
            <tr style="text-align:center; font-size:9px; font-weight:bold;">
                <td colspan="2" style="border:none; width:33%;">' . $logoCell . '</td>
                <td colspan="3" style="border:none; width:47%;">
                    <p>PROCESO DE MANTENIMIENTO</p>
                    <p>REPORTE ' . $tipoLabel . '</p>
                    <br>
                    <p>Seguimiento de equipos de ' . e($data['categoria_nombre']) . '</p>
                </td>
                <td colspan="1" style="border:none; width:20%;">
                    <br>VERSIÓN 01<br>' . now()->format('d-m-Y') . '<br>1-1
                </td>
            </tr>
        </table>';

        $pdf->writeHTMLCell(0, 0, '', '', $encabezado, 0, 1, false, true, 'C', true);
        $pdf->Ln(4);

        $infoResponsable = '
        <table cellpadding="2" cellspacing="4" style="width:100%; font-size:9px;">
            <tr>
                <td style="width:33%;"><strong>Responsable:</strong> ' . e($data['responsable_nombre']) . '</td>
                <td style="width:33%;"><strong>Check Mantenimiento de ' . e($data['tipo_categoria_label']) . '</strong></td>
                <td style="width:33%;"><strong>Fecha:</strong> ' . now()->format('d-m-Y') . '</td>
            </tr>
        </table>';

        $pdf->writeHTMLCell(0, 0, '', '', $infoResponsable, 0, 1, false, true, 'L', true);
        $pdf->Ln(4);

        $marca = $data['con_solucion'] ? ' X ' : ' ';

        if ($data['es_computadores']) {
            $columnas = ['Activación Windows', 'Anti-virus', 'Drive (Copia de Seguridad)', 'Limpieza general'];
        } else {
            $columnas = ['Revisión de funcionamiento', 'Limpieza general'];
        }

        $anchoFijo = 10 + 25 + 25; // ID + Descripción + Área
        $anchoChecklist = (100 - $anchoFijo) / count($columnas);

        $tabla = '<table cellpadding="2" border="1" style="width:100%; font-size:8px;">
            <tr style="text-align:center; font-weight:bold; text-transform:uppercase;">
                <th style="width:10%;">ID</th>
                <th style="width:25%;">Descripción</th>
                <th style="width:25%;">Área</th>';
        foreach ($columnas as $col) {
            $tabla .= '<th style="width:' . $anchoChecklist . '%;">' . e($col) . '</th>';
        }
        $tabla .= '</tr>';

        if (empty($data['equipos'])) {
            $tabla .= '<tr><td colspan="' . (3 + count($columnas)) . '" style="text-align:center;">Sin equipos</td></tr>';
        }

        foreach ($data['equipos'] as $equipo) {
            $tabla .= '<tr style="text-align:center;">
                <td>' . e((string) $equipo['id']) . '</td>
                <td style="text-align:left;">' . e($equipo['descripcion']) . '</td>
                <td>' . e($equipo['area']) . '</td>';
            foreach ($columnas as $col) {
                $tabla .= '<td>' . $marca . '</td>';
            }
            $tabla .= '</tr>';
        }
        $tabla .= '</table>';

        $pdf->writeHTML($tabla, true, false, true, false, '');
        $pdf->Ln(6);

        $pie = '
        <table cellpadding="6" style="width:100%; font-size:9px;" border="0">
            <tr>
                <td style="width:65%; vertical-align:bottom;">
                    <b>Confirmado por:</b><br><br>
                    <span style="border-bottom:1px solid #000; display:inline-block; width:80%;">&nbsp;</span>
                </td>
                <td style="width:35%; text-align:center; vertical-align:bottom;">
                    <b>Fecha</b><br><br>
                    <span style="border-bottom:1px solid #000; display:inline-block; width:80%;">' . now()->format('d-m-Y') . '</span>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($pie, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}

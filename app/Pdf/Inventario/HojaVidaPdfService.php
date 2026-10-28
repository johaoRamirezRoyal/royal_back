<?php

namespace App\Pdf\Inventario;

use App\Services\Cloudinary\CloudinaryService;
use TCPDF;

/**
 * Hoja de vida de un equipo (PDF horizontal) — recreación del legacy
 * (vistas/modulos/imprimir/listado_hoja_de_vida/hojaDeVida.php, FPDF) con TCPDF y HTML,
 * misma técnica que MantenimientoChecklistPdfService: el wrap multilínea de las celdas
 * lo resuelve TCPDF, no hace falta calcular alturas a mano como hacía el original.
 */
class HojaVidaPdfService
{
    /**
     * @param array{
     *   equipo: array{descripcion: string, id: int|string, marca: string, modelo: string, codigo: string, usuario: string, area: string, observacion: string},
     *   historial: array<int, array{fecha: string, usuario: string, area: string, estado: string, observacion: string, tiempo: string}>,
     * } $data
     */
    public function generate(array $data): string
    {
        $pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF
        {
            public function Header() {}

            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(127, 127, 127);
                $this->SetFont($this->getFontFamily(), 'I', 9);
                $this->Cell(0, 10, 'Pagina '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Hoja de vida');
        $pdf->SetSubject('Hoja de vida');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $equipo = $data['equipo'];
        // Logo institucional fijo (no el de la marca por dominio) — ver AGENTS.md "Logo en PDFs".
        $logoPath = app(CloudinaryService::class)->rutaLocalRoyalSchool('logotipoBackground.png');
        $logoCell = $logoPath && is_file($logoPath) ? '<img src="'.$logoPath.'" width="150">' : '';

        $encabezado = '
        <table border="1" cellpadding="6" style="width:100%; text-align:center;">
            <tr>
                <td style="width:21%;">'.$logoCell.'</td>
                <td style="width:58%; font-size:12px; font-weight:bold;"><br><br>PROCESO DE MANTENIMIENTO<br>HOJA DE VIDA</td>
                <td style="width:21%; font-size:9px; font-weight:bold;"><br>VERSIÓN 01<br>03-06-2025<br>1-1</td>
            </tr>
        </table>';
        $pdf->writeHTML($encabezado, true, false, true, false, '');

        $filas = [
            'Nombre del equipo:' => $equipo['descripcion'],
            'ID:' => (string) $equipo['id'],
            'Marca:' => $equipo['marca'],
            'Modelo:' => $equipo['modelo'],
            'Código Serial:' => $equipo['codigo'] !== '' ? $equipo['codigo'] : 'No registrado',
            'Usuario responsable:' => $equipo['usuario'],
            'Área:' => $equipo['area'],
            'Observación:' => $equipo['observacion'] !== '' ? $equipo['observacion'] : 'Sin observaciones',
        ];

        $info = '
        <p style="text-align:center; font-size:10px;"><i>Fecha de emisión: '.now()->format('d/m/Y').'</i></p>
        <table border="1" cellpadding="4" style="width:100%; font-size:9px; background-color:#dce6f1;">
            <tr><td style="text-align:center; font-size:11px; font-weight:bold;">INFORMACIÓN DEL EQUIPO '.e(mb_strtoupper($equipo['descripcion'])).'</td></tr>
        </table>
        <table border="1" cellpadding="4" style="width:100%; font-size:9px;">';
        foreach ($filas as $etiqueta => $valor) {
            $info .= '<tr><td style="width:50%;"><b>'.e($etiqueta).'</b></td><td style="width:50%;">'.e($valor).'</td></tr>';
        }
        $info .= '</table><br><br>';
        $pdf->writeHTML($info, true, false, true, false, '');

        $historial = '
        <p style="font-size:11px; font-weight:bold; color:#003366;">HISTORIAL DE REPORTES / MANTENIMIENTOS</p>
        <table border="1" cellpadding="3" style="width:100%; font-size:8px;">
            <thead>
                <tr style="background-color:#003366; color:#ffffff; text-align:center; font-weight:bold;">
                    <th style="width:11%;">Fecha</th><th style="width:18%;">Usuario</th><th style="width:18%;">Área</th>
                    <th style="width:11%;">Estado</th><th style="width:30%;">Observación</th><th style="width:12%;">Tiempo Resp.</th>
                </tr>
            </thead>';
        foreach ($data['historial'] as $i => $h) {
            $fondo = $i % 2 ? ' style="background-color:#f5f5f5;"' : '';
            $historial .= '<tr'.$fondo.'>
                <td style="width:11%; text-align:center;">'.e($h['fecha']).'</td>
                <td style="width:18%;">'.e($h['usuario']).'</td>
                <td style="width:18%;">'.e($h['area']).'</td>
                <td style="width:11%;">'.e($h['estado']).'</td>
                <td style="width:30%;">'.e($h['observacion']).'</td>
                <td style="width:12%; text-align:center;">'.e($h['tiempo']).'</td>
            </tr>';
        }
        $historial .= '</table>';
        $pdf->writeHTML($historial, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}

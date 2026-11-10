<?php

namespace App\Pdf\Inventario;

use App\Services\branding\MarcaDominioService;
use TCPDF;

/**
 * Carta de entrega de inventario (PDF vertical) — recreación del legacy
 * (vistas/modulos/imprimir/cartaEntregaOficial.php) con TCPDF y HTML, misma técnica que
 * ReporteOperativoPdfService. Las filas son los grupos del listado consolidado.
 */
class CartaEntregaPdfService
{
    /**
     * @param  array{institucion: string, filas: array<int, array{usuario: string, area: string, cantidad: int, descripcion: string, marca: string, estado: string, observacion: string}>}  $data
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
        $pdf->SetTitle('Carta de entrega');
        $pdf->SetSubject('Carta de entrega');
        $pdf->SetMargins(15, 10, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $logoPath = app(MarcaDominioService::class)->rutaLocalLogo();
        $logo = $logoPath && is_file($logoPath) ? '<img src="@'.base64_encode(file_get_contents($logoPath)).'" width="160">' : '';

        $inst = '<b>'.e($data['institucion']).'</b>';

        $filas = '';
        foreach ($data['filas'] as $f) {
            $filas .= '<tr>'
                .'<td style="width:16%;">'.e($f['usuario']).'</td>'
                .'<td style="width:14%;">'.e($f['area']).'</td>'
                .'<td style="width:6%;">'.e((string) $f['cantidad']).'</td>'
                .'<td style="width:24%;">'.e($f['descripcion']).'</td>'
                .'<td style="width:12%;">'.e($f['marca']).'</td>'
                .'<td style="width:10%;">'.e($f['estado']).'</td>'
                .'<td style="width:18%;">'.e($f['observacion']).'</td>'
                .'</tr>';
        }

        $html = '
        <div style="text-align:center;">'.$logo.'</div>
        <p style="text-align:center; font-size:11px; font-weight:bold;">ENTREGA DE INVENTARIO</p>
        <p style="font-size:9px;">'.now()->format('d/m/Y').'</p>
        <p style="text-align:justify; font-size:10px;">Por este conducto recibo del '.$inst.', el equipo que abajo se describe,
        mismo que será utilizado con fines de la institución '.e($data['institucion']).'; de igual forma acepto y me
        comprometo a no hacer mal uso del mismo.</p>
        <p style="text-align:justify; font-size:10px;">En caso de que el equipo sufra algún daño o pérdida, me comprometo a dar
        aviso en forma inmediata al área Administrativa del '.$inst.' y cubrir la reparación o reposición total del
        equipo según sea el caso.</p>
        <table border="1" cellpadding="2" style="width:100%; font-size:8px; text-align:center;">
            <tr style="font-weight:bold;">
                <th style="width:16%;">USUARIO</th><th style="width:14%;">ÁREA</th><th style="width:6%;">CANT</th>
                <th style="width:24%;">DESCRIPCIÓN</th><th style="width:12%;">MARCA</th><th style="width:10%;">ESTADO</th>
                <th style="width:18%;">OBSERVACIÓN</th>
            </tr>'.$filas.'
        </table>
        <p style="text-align:justify; font-size:10px;">Me comprometo a entregar el equipo del área en el momento en que el
        '.$inst.' me lo requiera.</p>
        <br><br><br><br><br><br><br><br><br>
        <table cellpadding="2" style="width:100%; font-size:8px; text-align:center;">
            <tr>
                <td style="width:50%;">________________________________<br><b>RESPONSABLE DEL SALÓN</b></td>
                <td style="width:50%;">________________________________<br><b>ENTREGADO POR</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}

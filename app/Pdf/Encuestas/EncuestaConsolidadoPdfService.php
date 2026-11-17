<?php

namespace App\Pdf\Encuestas;

use Illuminate\Support\Carbon;
use TCPDF;

/**
 * Informe consolidado de una encuesta de satisfacción (ver
 * EncuestasServices::generarPdfResultados): ficha con los filtros aplicados, totales por
 * pregunta, respuestas de texto libre con el `#id · fecha` de su envío y un anexo con
 * todos los envíos incluidos. Basado en HTML (writeHTML), igual que
 * `EvaluacionRespuestaPdfService`. Solo dibuja: los datos y el logo los resuelve el service.
 */
class EncuestaConsolidadoPdfService
{
    /**
     * @param  array<int, array{id_pregunta: int, texto: string, opciones?: iterable, respuestas_texto?: iterable}>  $preguntas  `data.preguntas` de resultados()
     * @param  array<int, array{id: int, created_at: string, reserva_titulo: ?string, salon: string, respuestas: iterable}>  $envios  `data` de envios()
     * @param  array<string, string>  $ficha  etiqueta => valor
     */
    public function generate(array $preguntas, array $envios, array $ficha, ?string $logoPath): string
    {
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF
        {
            public function Header() {}

            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(150, 150, 150);
                $this->SetFont($this->getFontFamily(), 'I', 8);
                $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetTitle('Informe de encuesta');
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $this->drawHeader($pdf, $ficha['Encuesta'] ?? '', $logoPath, $ficha['Total de respuestas'] ?? '0');
        $this->drawFicha($pdf, $ficha);

        $textosPorPregunta = $this->textosPorPregunta($envios);

        foreach ($preguntas as $pregunta) {
            $html = '<span style="font-size:10.5pt; font-weight:bold; color:#1e293b;">'.e($pregunta['texto']).'</span>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(1);

            if (isset($pregunta['opciones'])) {
                $pdf->writeHTML($this->tablaOpciones(collect($pregunta['opciones'])->all()), true, false, true, false, '');
            } else {
                $pdf->writeHTML($this->listaTextos($textosPorPregunta[$pregunta['id_pregunta']] ?? []), true, false, true, false, '');
            }

            $pdf->Ln(4);
        }

        if (! empty($envios)) {
            $pdf->writeHTML('<span style="font-size:11pt; font-weight:bold;">Anexo: envíos incluidos</span>', true, false, true, false, '');
            $pdf->Ln(1);
            $pdf->writeHTML($this->tablaEnvios($envios), true, false, true, false, '');
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Mismo encabezado que los reportes (HistorialChecksPdfService, HojaVidaPdfService):
     * tabla con borde de tres celdas — logo de la marca por dominio, proceso + título y
     * datos de emisión.
     */
    private function drawHeader(TCPDF $pdf, string $tituloEncuesta, ?string $logoPath, string $totalRespuestas): void
    {
        $logoCell = $logoPath && is_file($logoPath) ? '<img src="@'.base64_encode(file_get_contents($logoPath)).'" width="120">' : '';

        $html = '
        <table border="1" cellpadding="6" style="width:100%; text-align:center;">
            <tr>
                <td style="width:24%;">'.$logoCell.'</td>
                <td style="width:52%; font-size:12px; font-weight:bold;"><br>GESTIÓN HUMANA<br>INFORME DE ENCUESTA<br><span style="font-size:10px; font-weight:normal;">'.e($tituloEncuesta).'</span></td>
                <td style="width:24%; font-size:9px;"><br><b>'.e($totalRespuestas).' respuesta'.($totalRespuestas === '1' ? '' : 's').'</b><br>Emisión: '.now()->format('d/m/Y').'<br>'.now()->format('H:i').'</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(4);
    }

    private function drawFicha(TCPDF $pdf, array $ficha): void
    {
        $filas = '';
        foreach ($ficha as $etiqueta => $valor) {
            $filas .= '<tr>
                <td style="width:30%; background-color:#f1f5f9; color:#475569; font-weight:bold;">'.e($etiqueta).'</td>
                <td style="width:70%; color:#1e293b;">'.e($valor).'</td>
            </tr>';
        }

        $pdf->writeHTML('<table cellpadding="4" border="1" style="border-color:#e2e8f0; font-size:9pt;">'.$filas.'</table>', true, false, true, false, '');
        $pdf->Ln(5);
    }

    /** @param array<int, array{texto: string, total: int}> $opciones */
    private function tablaOpciones(array $opciones): string
    {
        if (empty($opciones)) {
            return '<span style="font-size:9pt; color:#64748b;">Pregunta sin opciones configuradas.</span>';
        }

        $suma = array_sum(array_column($opciones, 'total'));

        $filas = '';
        foreach ($opciones as $opcion) {
            $pct = $suma ? round($opcion['total'] / $suma * 100) : 0;
            $filas .= '<tr>
                <td style="width:50%;">'.e($opcion['texto']).'</td>
                <td style="width:15%; text-align:right;">'.$opcion['total'].'</td>
                <td style="width:12%; text-align:right;">'.$pct.'%</td>
                <td style="width:23%;"><table cellpadding="0" style="width:100%;"><tr>'
                    .($pct > 0 ? '<td style="width:'.$pct.'%; background-color:#3b82f6;">&nbsp;</td>' : '')
                    .($pct < 100 ? '<td style="width:'.(100 - $pct).'%; background-color:#e2e8f0;">&nbsp;</td>' : '')
                .'</tr></table></td>
            </tr>';
        }

        return '<table cellpadding="3" border="1" style="border-color:#e2e8f0; font-size:9pt;">
            <tr style="background-color:#f1f5f9; font-weight:bold; color:#475569;">
                <td style="width:50%;">Opción</td>
                <td style="width:15%; text-align:right;">Respuestas</td>
                <td style="width:12%; text-align:right;">%</td>
                <td style="width:23%;"></td>
            </tr>'.$filas.'</table>';
    }

    /** @param array<int, array{id: int, fecha: string, texto: string}> $textos */
    private function listaTextos(array $textos): string
    {
        if (empty($textos)) {
            return '<span style="font-size:9pt; color:#64748b;">Sin respuestas.</span>';
        }

        $filas = '';
        foreach ($textos as $t) {
            $filas .= '<tr>
                <td style="width:22%; color:#64748b;">#'.$t['id'].' &middot; '.e($t['fecha']).'</td>
                <td style="width:78%; color:#1e293b;">'.nl2br(e($t['texto'])).'</td>
            </tr>';
        }

        return '<table cellpadding="3" border="1" style="border-color:#e2e8f0; font-size:9pt;">'.$filas.'</table>';
    }

    private function tablaEnvios(array $envios): string
    {
        $filas = '';
        foreach ($envios as $envio) {
            $filas .= '<tr>
                <td style="width:12%;">#'.$envio['id'].'</td>
                <td style="width:22%;">'.e($this->fecha($envio['created_at'])).'</td>
                <td style="width:28%;">'.e($envio['salon']).'</td>
                <td style="width:38%;">'.e($envio['reserva_titulo'] ?? '—').'</td>
            </tr>';
        }

        return '<table cellpadding="3" border="1" style="border-color:#e2e8f0; font-size:8.5pt;">
            <tr style="background-color:#f1f5f9; font-weight:bold; color:#475569;">
                <td style="width:12%;">Envío</td>
                <td style="width:22%;">Fecha</td>
                <td style="width:28%;">Salón</td>
                <td style="width:38%;">Reserva</td>
            </tr>'.$filas.'</table>';
    }

    /** @return array<int, array<int, array{id: int, fecha: string, texto: string}>> id_pregunta => textos */
    private function textosPorPregunta(array $envios): array
    {
        $porPregunta = [];
        foreach ($envios as $envio) {
            foreach ($envio['respuestas'] as $respuesta) {
                if (! empty($respuesta['valor_texto'])) {
                    $porPregunta[$respuesta['id_pregunta']][] = [
                        'id' => $envio['id'],
                        'fecha' => $this->fecha($envio['created_at']),
                        'texto' => $respuesta['valor_texto'],
                    ];
                }
            }
        }

        return $porPregunta;
    }

    private function fecha(?string $fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->format('d/m/Y H:i') : '—';
    }
}

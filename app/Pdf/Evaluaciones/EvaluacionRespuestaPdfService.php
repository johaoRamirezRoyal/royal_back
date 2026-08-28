<?php

namespace App\Pdf\Evaluaciones;

use App\Models\Evaluaciones\EvaluacionRespuestaEvaluacion;
use TCPDF;

/**
 * Reporte simple (no pixel-perfect, a diferencia de PazYSalvoPdfService) de una
 * evaluación de desempeño ya respondida: datos del evaluado, periodo/año, y por
 * sección/pregunta la(s) opción(es) elegidas + comentario. Se usa tanto para el envío
 * automático por correo como para el botón "Generar PDF"/"Reenviar correo" del
 * coordinador.
 */
class EvaluacionRespuestaPdfService
{
    public function generate(EvaluacionRespuestaEvaluacion $respuesta): string
    {
        $respuesta->loadMissing([
            'evaluacion.servicio',
            'evaluado',
            'nivel',
            'periodo.anioEscolar',
            'respuestasPreguntas.pregunta.seccion',
            'respuestasPreguntas.opcion',
        ]);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $evaluacion = $respuesta->evaluacion;
        $evaluado = $respuesta->evaluado;

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Evaluación de desempeño', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, $evaluacion?->titulo ?? '', 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', '', 10);
        $periodo = $respuesta->periodo;
        $anio = $periodo?->anioEscolar;
        $periodoLabel = $periodo
            ? sprintf('Periodo %s%s', $periodo->numero, $anio ? " – {$anio->anio_inicio}/{$anio->anio_fin}" : '')
            : 'N/A';

        $filas = [
            ['Evaluado', trim(($evaluado?->nombre ?? '') . ' ' . ($evaluado?->apellido ?? ''))],
            ['Documento', $evaluado?->documento ?? ''],
            ['Nivel', $respuesta->nivel?->nombre ?? ''],
            ['Servicio', $evaluacion?->servicio?->nombre ?? ''],
            ['Periodo', $periodoLabel],
            ['Fecha', optional($respuesta->completada_en)->format('d/m/Y H:i') ?? ''],
        ];

        foreach ($filas as [$label, $valor]) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(35, 6, $label . ':', 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, (string) $valor, 0, 1);
        }

        $pdf->Ln(4);

        $porSeccion = $respuesta->respuestasPreguntas->groupBy(fn ($rp) => $rp->pregunta?->id_seccion ?? 0);

        foreach ($porSeccion as $respuestasSeccion) {
            $seccion = $respuestasSeccion->first()?->pregunta?->seccion;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(0, 8, $seccion?->titulo ?? 'Sección', 0, 1, 'L', true);
            $pdf->Ln(1);

            foreach ($respuestasSeccion as $rp) {
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->MultiCell(0, 6, $rp->pregunta?->texto ?? '', 0, 'L');

                $pdf->SetFont('helvetica', '', 10);
                $valor = $rp->opcion?->texto ?? $rp->valor_texto ?? '—';
                $pdf->MultiCell(0, 6, 'Respuesta: ' . $valor, 0, 'L');

                if (!empty($rp->comentario)) {
                    $pdf->SetFont('helvetica', 'I', 9);
                    $pdf->MultiCell(0, 6, 'Observación: ' . $rp->comentario, 0, 'L');
                }

                $pdf->Ln(2);
            }

            $pdf->Ln(2);
        }

        return $pdf->Output('', 'S');
    }
}

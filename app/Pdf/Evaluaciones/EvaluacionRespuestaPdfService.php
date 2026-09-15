<?php

namespace App\Pdf\Evaluaciones;

use App\Models\Evaluaciones\EvaluacionRespuestaEvaluacion;
use App\Services\evaluaciones\EvaluacionesServices;
use TCPDF;

/**
 * Reporte de una evaluación de desempeño ya respondida, con estética de resultado de
 * prueba estandarizada (SABER/ICFES/PISA): header institucional, ficha de datos,
 * resultado general destacado, tarjetas de puntaje por sección con su nivel de
 * desempeño (mismos umbrales/colores que `Ver.tsx` en el frontend, para consistencia
 * visual), y las respuestas de texto libre/observaciones como tarjetas aparte. Basado
 * en HTML (writeHTML), igual que `MantenimientoChecklistPdfService` — no hay un diseño
 * pixel-perfect que replicar acá, así que no vale la pena el posicionamiento absoluto
 * que sí usa `PazYSalvoPdfService`.
 */
class EvaluacionRespuestaPdfService
{
    // Nombre único de la institución (proyecto single-tenant) — mismo valor que
    // VITE_INSTITUTION en el .env del frontend.
    private const INSTITUCION_NOMBRE = 'Colegio Real Royal School';

    private string $logoPath;

    public function __construct(private EvaluacionesServices $evaluacionesServices, ?string $logoPath = null)
    {
        // Escudo circular (fondo blanco) — el que sí se distingue sobre la banda navy del
        // header; `logotipoBackground-white.png`, en la misma carpeta, tiene fondo negro
        // sólido y se vería como un bloque negro ahí.
        $this->logoPath = $logoPath ?? storage_path('app/public/royal-school/logotipoMarca.png');
    }

    public function setLogoPath(?string $logoPath): void
    {
        if ($logoPath) {
            $this->logoPath = $logoPath;
        }
    }

    /** Paleta por nivel de desempeño — mismos umbrales/colores que `nivelDesempeno` en Ver.tsx. */
    private function nivel(float $promedio): array
    {
        if ($promedio < 60) return ['label' => 'Bajo', 'bg' => '#fee2e2', 'text' => '#b91c1c', 'solid' => '#ef4444'];
        if ($promedio < 80) return ['label' => 'Medio', 'bg' => '#fef3c7', 'text' => '#b45309', 'solid' => '#f59e0b'];
        if ($promedio < 90) return ['label' => 'Eficiente', 'bg' => '#dbeafe', 'text' => '#1d4ed8', 'solid' => '#3b82f6'];
        return ['label' => 'Alto', 'bg' => '#dcfce7', 'text' => '#15803d', 'solid' => '#22c55e'];
    }

    public function generate(EvaluacionRespuestaEvaluacion $respuesta): string
    {
        $respuesta->loadMissing([
            'evaluacion.servicio',
            'evaluacion.secciones.preguntas.opciones',
            'evaluado',
            'usuario',
            'nivel',
            'periodo.anioEscolar',
            'respuestasPreguntas.pregunta.tipo',
            'respuestasPreguntas.pregunta.seccion',
            'respuestasPreguntas.opcion',
        ]);

        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
            public function Header() {}
            public function Footer()
            {
                $this->SetY(-15);
                $this->SetTextColor(150, 150, 150);
                $this->SetFont($this->getFontFamily(), 'I', 8);
                $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor(self::INSTITUCION_NOMBRE);
        $pdf->SetTitle('Evaluación de desempeño');
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $evaluacion = $respuesta->evaluacion;
        $evaluado = $respuesta->evaluado;
        $periodo = $respuesta->periodo;
        $anio = $periodo?->anioEscolar;

        $this->drawHeader($pdf, $evaluacion?->titulo ?? '');
        $this->drawFicha($pdf, $respuesta, $evaluado, $periodo, $anio, $evaluacion);

        $resultado = $this->evaluacionesServices->calcularPuntajeRespuesta($respuesta);

        $this->drawResultadoGeneral($pdf, (float) $resultado['promedio_general']);
        $this->drawTarjetasSeccion($pdf, $resultado['por_seccion']);

        [$textosLibres, $observaciones] = $this->extraerTextosLibresYObservaciones($respuesta);

        if (!empty($textosLibres)) {
            $pdf->writeHTML('<span style="font-size:11pt; font-weight:bold;">Observaciones Generales por Sección</span>', true, false, true, false, '');
            $pdf->Ln(2);
            $pdf->writeHTML($this->tarjetasTexto($textosLibres, '#f8fafc', '#334155'), true, false, true, false, '');
            $pdf->Ln(4);
        }

        if (!empty($observaciones)) {
            $pdf->writeHTML('<span style="font-size:11pt; font-weight:bold;">Observaciones</span>', true, false, true, false, '');
            $pdf->Ln(2);
            $pdf->writeHTML($this->tarjetasTexto($observaciones, '#fffbeb', '#78350f'), true, false, true, false, '');
        }

        return $pdf->Output('', 'S');
    }

    private function drawHeader(TCPDF $pdf, string $tituloEvaluacion): void
    {
        $logoCell = is_file($this->logoPath) ? '<img src="' . $this->logoPath . '" width="32">' : '';

        $html = '
        <table cellpadding="4" style="width:100%; border-bottom:2px solid #b91c1c;">
            <tr>
                <td style="width:20%; text-align:center;">' . $logoCell . '</td>
                <td style="width:80%; color:#1e293b;">
                    <span style="font-size:15pt; font-weight:bold; color:#1e3a8a;">' . e(self::INSTITUCION_NOMBRE) . '</span><br>
                    <span style="font-size:10pt; color:#475569;">Evaluación de Desempeño &mdash; ' . e($tituloEvaluacion) . '</span>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(3);
    }

    private function drawFicha(TCPDF $pdf, EvaluacionRespuestaEvaluacion $respuesta, $evaluado, $periodo, $anio, $evaluacion): void
    {
        $periodoLabel = $periodo
            ? 'Periodo ' . $periodo->numero . ($anio ? " ({$anio->anio_inicio}/{$anio->anio_fin})" : '')
            : 'N/A';
        $evaluadoNombre = trim(($evaluado?->nombre ?? '') . ' ' . ($evaluado?->apellido ?? '')) ?: 'N/A';
        $evaluadorNombre = $respuesta->anonima
            ? 'Anónimo'
            : (trim(($respuesta->usuario?->nombre ?? '') . ' ' . ($respuesta->usuario?->apellido ?? '')) ?: 'N/A');
        $fecha = optional($respuesta->completada_en)->format('d/m/Y h:i A') ?? 'N/A';

        $html = '
        <table cellpadding="4" style="width:100%; font-size:9pt; background-color:#f8fafc;" border="1" bordercolor="#e2e8f0">
            <tr>
                <td style="width:50%;"><strong>Evaluado:</strong> ' . e($evaluadoNombre) . '</td>
                <td style="width:50%;"><strong>Evaluador:</strong> ' . e($evaluadorNombre) . '</td>
            </tr>
            <tr>
                <td><strong>Periodo:</strong> ' . e($periodoLabel) . '</td>
                <td><strong>Fecha:</strong> ' . e($fecha) . '</td>
            </tr>
            <tr>
                <td><strong>Servicio:</strong> ' . e($evaluacion?->servicio?->nombre ?? 'N/A') . '</td>
                <td><strong>Nivel:</strong> ' . e($respuesta->nivel?->nombre ?? 'N/A') . '</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(4);
    }

    private function drawResultadoGeneral(TCPDF $pdf, float $promedioGeneral): void
    {
        $nivel = $this->nivel($promedioGeneral);

        $html = '
        <table cellpadding="8" style="width:100%; background-color:' . $nivel['bg'] . '; border:2px solid ' . $nivel['solid'] . ';">
            <tr>
                <td style="width:60%; text-align:left;">
                    <span style="font-size:9pt; font-weight:bold; color:#475569;">RESULTADO GENERAL</span><br>
                    <span style="font-size:20pt; font-weight:bold; color:' . $nivel['text'] . ';">' . $promedioGeneral . '%</span>
                </td>
                <td style="width:40%; text-align:right; vertical-align:middle;">
                    <span style="font-size:13pt; font-weight:bold; color:' . $nivel['text'] . ';">Nivel ' . $nivel['label'] . '</span>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(4);
    }

    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $porSeccion */
    private function drawTarjetasSeccion(TCPDF $pdf, $porSeccion): void
    {
        if ($porSeccion->isEmpty()) {
            return;
        }

        $pdf->writeHTML('<span style="font-size:11pt; font-weight:bold;">Resultados por sección</span>', true, false, true, false, '');
        $pdf->Ln(2);

        // array_chunk arma filas completas — un `<tr>` que termina vacío (sin ningún
        // `<td>`) hace que el parser de TCPDF reviente con "Undefined array key
        // startcolumn", que es lo que pasaba al ir abriendo/cerrando filas a mano
        // llevando la cuenta con un módulo.
        $html = '<table cellpadding="0" cellspacing="3" style="width:100%;">';

        foreach (array_chunk($porSeccion->all(), 3) as $fila) {
            $html .= '<tr>';
            foreach ($fila as $seccion) {
                $nivel = $this->nivel((float) $seccion['promedio']);
                $html .= '
                <td style="width:33%; vertical-align:top;">
                    <table cellpadding="5" style="width:100%; border:1px solid #e2e8f0; border-left:4px solid ' . $nivel['solid'] . ';">
                        <tr><td>
                            <span style="font-size:8.5pt; font-weight:bold;">' . e($seccion['titulo']) . '</span><br>
                            <span style="font-size:16pt; font-weight:bold; color:' . $nivel['text'] . ';">' . $seccion['promedio'] . '%</span><br>
                            <span style="font-size:7.5pt; color:#64748b;">Pondera ' . $seccion['porcentaje_ponderacion'] . '% &middot; ' . $nivel['label'] . '</span>
                        </td></tr>
                    </table>
                </td>';
            }
            for ($i = count($fila); $i < 3; $i++) {
                $html .= '<td style="width:33%;"></td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(4);
    }

    /**
     * Separa las respuestas de texto libre (siempre se muestran, ver ítem 3 — no
     * cuentan para la nota pero sí son retroalimentación cualitativa) de las
     * observaciones opcionales (`permite_comentario`) de cualquier otra pregunta, cada
     * una con el título de su sección para armar las tarjetas. Misma lógica que
     * `Ver.tsx` en el frontend, contra la estructura ya cargada de `respuestasPreguntas`.
     *
     * @return array{0: array<int, array{seccion: string, pregunta: string, texto: string}>, 1: array<int, array{seccion: string, pregunta: string, texto: string}>}
     */
    private function extraerTextosLibresYObservaciones(EvaluacionRespuestaEvaluacion $respuesta): array
    {
        $porPregunta = $respuesta->respuestasPreguntas->groupBy('id_pregunta');

        $textosLibres = [];
        $observaciones = [];

        foreach ($porPregunta as $respuestasPregunta) {
            $rp = $respuestasPregunta->first();
            $pregunta = $rp?->pregunta;
            if (!$pregunta) {
                continue;
            }

            $seccionTitulo = $pregunta->seccion?->titulo ?? 'Sin sección';
            $slug = $pregunta->tipo?->slug;

            if ($slug === 'texto_libre') {
                if (!empty($rp->valor_texto)) {
                    $textosLibres[] = ['seccion' => $seccionTitulo, 'pregunta' => $pregunta->texto, 'texto' => $rp->valor_texto];
                }
                continue;
            }

            if (!empty($rp->comentario)) {
                $observaciones[] = ['seccion' => $seccionTitulo, 'pregunta' => $pregunta->texto, 'texto' => $rp->comentario];
            }
        }

        return [$textosLibres, $observaciones];
    }

    /** @param array<int, array{seccion: string, pregunta: string, texto: string}> $items */
    private function tarjetasTexto(array $items, string $bg, string $textColor): string
    {
        // Mismo cuidado que en drawTarjetasSeccion: array_chunk evita dejar una fila
        // vacía al final, que es lo que hacía reventar a TCPDF con "Undefined array key
        // startcolumn".
        $html = '<table cellpadding="0" cellspacing="3" style="width:100%;">';

        foreach (array_chunk($items, 2) as $fila) {
            $html .= '<tr>';
            foreach ($fila as $item) {
                $html .= '
                <td style="width:50%; vertical-align:top;">
                    <table cellpadding="5" style="width:100%; background-color:' . $bg . '; border:1px solid #e2e8f0;">
                        <tr><td>
                            <span style="font-size:7pt; font-weight:bold; color:#94a3b8;">' . mb_strtoupper(e($item['seccion'])) . '</span><br>
                            <span style="font-size:8pt; color:#475569;">' . e($item['pregunta']) . '</span><br>
                            <span style="font-size:9pt; font-style:italic; color:' . $textColor . ';">&ldquo;' . e($item['texto']) . '&rdquo;</span>
                        </td></tr>
                    </table>
                </td>';
            }
            if (count($fila) < 2) {
                $html .= '<td style="width:50%;"></td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

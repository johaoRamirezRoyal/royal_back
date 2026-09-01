<?php

namespace App\Pdf\Instituciones;

use App\Models\Instituciones\CartaRecomendacion;
use TCPDF;

/**
 * Genera el PDF de una carta de recomendación ya diligenciada.
 *
 * Recrea el diseño real de los formatos originales en
 * src/assets/Admissions/LettersOfRecommendation (frontend): CARTA RECOMENDACION
 * COORD-PISCOL ESP.pdf / COORD PSICOL ENG.pdf para Coordinador/Psicólogo (ES/EN), y
 * CARTA RECOMENDACION P AND L.pdf para Play and Learn — mismo encabezado con el escudo
 * del colegio, tablas de SI/NO/COMENTARIOS y pie de página; ambos comparten los
 * helpers de dibujo de esta clase, cada uno con su propio título, párrafo de
 * instrucciones y campos.
 */
class CartaRecomendacionPdfService
{
    private const NAVY = [11, 31, 94];
    private const GRAY_LIGHT = [225, 225, 225];
    private const RED = [191, 4, 17];
    private const GRID_GRAY = [150, 150, 170];

    private const MARGIN = 40.0;
    private const PAGE_W = 595.28;
    private const CONTENT_W = self::PAGE_W - self::MARGIN * 2;
    private const RIGHT_X = self::PAGE_W - self::MARGIN;

    /** Filas de la tabla "Información del estudiante", en el orden exacto del PDF original.
     * `fecha_remision` solo existe en la versión en español — el formato en inglés no la
     * incluye como fila propia (ver CARTA RECOMENDACION COORD PSICOL ENG.pdf). */
    private const FILAS_ESTUDIANTE = [
        ['tipo' => 'yesno', 'key' => 'dificultad_aprendizaje', 'es' => '¿Presenta alguna dificultad en la adquisición del aprendizaje?', 'en' => 'Does the student show difficulty in acquisition of learning?'],
        ['tipo' => 'yesno', 'key' => 'remitido_terapeuta', 'es' => '¿Ha sido remitido a trabajar con algún terapeuta? En caso afirmativo responder a las preguntas 3, 4, 5 y 6.', 'en' => 'Has the student been referred to work with an external therapist? If your answer is yes, please respond questions 3, 4, and 5.'],
        ['tipo' => 'texto', 'key' => 'fecha_remision', 'es' => '¿Fecha de remisión?', 'en' => '', 'soloEs' => true],
        ['tipo' => 'terapia', 'key' => 'tipo_terapia', 'es' => '¿Tipo de terapia(s) y nombre(s) de terapeuta(s)?', 'en' => 'Type of therapy.'],
        ['tipo' => 'yesno', 'key' => 'cumple_terapia', 'es' => '¿Cumple con la(s) terapia(s) puntualmente?', 'en' => 'Does the student attend therapy regularly, as directed by therapist?'],
        ['tipo' => 'yesno', 'key' => 'informe_progreso', 'es' => '¿Ha presentado informe de progreso de la(s) terapia(s)?', 'en' => 'Have you received therapy progress report?'],
        ['tipo' => 'yesno', 'key' => 'dificultades_salud', 'es' => '¿Ha presentado dificultades con su salud? Problemas físicos o emocionales?', 'en' => 'Has the student have any health, physical or emotional problems?'],
        ['tipo' => 'yesno', 'key' => 'problemas_disciplina', 'es' => '¿Tiene problemas de disciplina o comportamentales?', 'en' => 'Does the student have discipline or behavior problems?'],
        ['tipo' => 'yesno', 'key' => 'actitud_positiva', 'es' => '¿Muestra una actitud positiva hacia el trabajo escolar?', 'en' => 'Does the student show a positive attitude towards school work?'],
        ['tipo' => 'yesno', 'key' => 'relacion_pares', 'es' => '¿Se relaciona positivamente con sus pares?', 'en' => 'Does the student have positive personal relations with his pairs?'],
        ['tipo' => 'yesno', 'key' => 'respeto_normas', 'es' => '¿Muestra respeto ante los límites y normas?', 'en' => 'Does the student show respect for the school rules?'],
    ];

    private const FILAS_PADRES = [
        ['key' => 'reciben_recomendaciones', 'es' => '¿Reciben con agrado recomendaciones del Jardín/Colegio en cuanto al proceso de aprendizaje de su hijo(a) y colaboran con el mismo?', 'en' => 'Do you consider parents are cooperative?'],
        ['key' => 'cumplen_normas', 'es' => '¿Cumplen con las normas del Jardín/Colegio?', 'en' => "Do parents follow the school's policy?"],
        ['key' => 'colaboran_actividades', 'es' => '¿Son colaboradores e involucrados con las actividades del Jardín/Colegio?', 'en' => 'Do parents participate in school promoted activities?'],
        ['key' => 'obligaciones_financieras', 'es' => '¿Cumplen puntualmente sus obligaciones financieras con el Jardín/Colegio?', 'en' => "Do parents meet their school's financial obligations on time?"],
    ];

    private string $logoPath;

    public function __construct(?string $logoPath = null)
    {
        $this->logoPath = $logoPath ?? storage_path('app/public/images/instituciones/logo.png');
    }

    public function generate(CartaRecomendacion $carta): string
    {
        $carta->loadMissing('institucion');

        $datos = $carta->datos ?? [];
        $idioma = $carta->idioma === 'en' ? 'en' : 'es';
        $esPlayAndLearn = $carta->institucion?->tipo_documento === 'play_and_learn';
        $nombreInstitucion = $carta->institucion?->nombre ?? '';

        if ($esPlayAndLearn) {
            return $this->generatePlayAndLearn($datos, $nombreInstitucion);
        }

        return $this->generateCoordinadorPsicologo($datos, $idioma, $nombreInstitucion);
    }

    // ── Formato Coordinador/Psicólogo — recreación del diseño original ─────────────

    private function generateCoordinadorPsicologo(array $datos, string $idioma, string $nombreInstitucion): string
    {
        $en = $idioma === 'en';

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OMNIA');
        $pdf->SetTitle($en ? 'Recommendation Letter' : 'Carta de Recomendación');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->SetAutoPageBreak(true, 55);
        $pdf->AddPage();

        $this->drawEncabezado(
            $pdf,
            $en ? 'ADMISSIONS' : 'ADMISIONES',
            $en ? 'DIRECTOR, LEVEL COORDINATOR, OR PSYCHOLOGIST RECOMMENDATION' : 'CARTA DE RECOMENDACIÓN COORDINACIÓN / PSICOLOGÍA',
            $en ? '01-08-2019' : '01-09-2021'
        );

        $pdf->SetY(130);
        $this->drawParrafoInstrucciones($pdf, $en);

        $pdf->Ln(10);
        $this->drawCamposEncabezado($pdf, $en, $datos, $nombreInstitucion);

        $pdf->Ln(8);
        // Pregunta 1 existe igual en ambos idiomas del PDF original (CARTA RECOMENDACION
        // COORD PSICOL ENG.pdf trae literalmente "1. How long ago do you know the
        // student?") — antes se forzaba en blanco para EN por error.
        $this->drawPregunta($pdf, '1.', $en ? 'How long ago do you know the student?' : '¿Cuánto tiempo hace que conoce al estudiante?', $datos['tiempo_conoce'] ?? '');
        $pdf->Ln(4);
        $this->drawPregunta($pdf, '2.', $en ? "Which, do you consider, are the student's strengths?" : '¿Cuáles considera son las fortalezas del estudiante?', $datos['fortalezas'] ?? '');
        $pdf->Ln(4);
        $this->drawPregunta($pdf, '3.', $en ? "Which, do you consider, are the student's weaknesses?" : '¿Cuáles considera son las debilidades del estudiante?', $datos['debilidades'] ?? '');

        $pdf->Ln(10);
        $this->drawTablaRespuestas(
            $pdf,
            $en ? 'INFORMATION ABOUT THE STUDENT' : 'INFORMACIÓN DEL ESTUDIANTE',
            $en ? 'YES' : 'SI',
            $en ? 'NO' : 'NO',
            $en ? 'COMMENTS' : 'COMENTARIOS',
            self::FILAS_ESTUDIANTE,
            $datos['estudiante'] ?? [],
            $idioma
        );

        // "Información de los padres" arranca en una página nueva junto con todo lo que le
        // sigue (comentario adicional + datos de quien diligencia) — a pedido explícito, no
        // se deja que la tabla se reparta a la mitad entre las dos secciones.
        $pdf->AddPage();
        $this->drawTablaRespuestas(
            $pdf,
            $en ? 'INFORMATION ABOUT PARENTS' : 'INFORMACIÓN DE LOS PADRES',
            $en ? 'YES' : 'SI',
            $en ? 'NO' : 'NO',
            $en ? 'COMMENTS' : 'COMENTARIOS',
            self::FILAS_PADRES,
            $datos['padres'] ?? [],
            $idioma
        );

        $pdf->Ln(12);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->MultiCell(self::CONTENT_W, 12, $en
            ? 'Please, include any additional information you may consider necessary for a detailed admission evaluation of the student above mentioned, as a candidate to be admitted to the Royal School.'
            : 'Favor incluir cualquier comentario adicional que considere pueda servirnos para hacer una evaluación minuciosa del estudiante como candidato para admisión a nuestro Colegio.', 0, 'L');
        $pdf->Ln(3);
        $pdf->MultiCell(self::CONTENT_W, 12, ($datos['comentario_adicional'] ?? '') !== '' ? $datos['comentario_adicional'] : '-', 0, 'L');

        $pdf->Ln(14);
        $this->drawFirmante($pdf, $en, $datos['firmante'] ?? [], $nombreInstitucion);

        $this->drawFooter($pdf);

        return $pdf->Output('', 'S');
    }

    private function drawEncabezado(TCPDF $pdf, string $tituloDerecha, string $tituloBarra, string $fechaVersion): void
    {
        $headerH = 100.0;

        $pdf->SetFillColorArray(self::NAVY);
        $pdf->Rect(0, 0, self::PAGE_W, $headerH, 'F');

        $pdf->SetFillColorArray(self::GRAY_LIGHT);
        $pdf->Polygon([595.28, 0, 595.28, $headerH, 370, $headerH, 470, 0], 'F');

        // Logotipo blanco (escudo + wordmark en una sola imagen, fondo ya recortado a
        // transparente) — pensado para fondos oscuros como este encabezado navy.
        if (is_file($this->logoPath)) {
            $pdf->Image($this->logoPath, self::MARGIN, 18, 164.6, 64, 'PNG');
        }

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColorArray(self::NAVY);
        $pdf->RoundedRect(465, 15, 60, 15, 3, '1111', 'F', [], self::GRAY_LIGHT);
        $pdf->SetXY(465, 19);
        $pdf->Cell(60, 8, 'VERSIÓN 02', 0, 0, 'C');
        $pdf->SetXY(525, 19);
        $pdf->Cell(50, 8, $fechaVersion, 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 17);
        $pdf->SetXY(370, 68);
        $pdf->Cell(self::RIGHT_X - 370, 20, $tituloDerecha, 0, 0, 'R');

        $pdf->SetFillColorArray(self::GRAY_LIGHT);
        $pdf->Rect(0, $headerH, self::PAGE_W, 23, 'F');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColorArray(self::NAVY);
        $pdf->SetXY(0, $headerH + 6);
        $pdf->Cell(self::PAGE_W, 12, $tituloBarra, 0, 0, 'C');

        $pdf->SetTextColorArray([0, 0, 0]);
    }

    private function drawParrafoInstrucciones(TCPDF $pdf, bool $en): void
    {
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->MultiCell(self::CONTENT_W, 12.5, $en
            ? "This format is to be filled by the director, coordinator or psychologist of the school to which applicant is actually attending classes. It should be returned in a sealed envelope to the Royal School's Admissions Office or mailed to Admissions Coordinator: giovanna.bayter@royalschool.edu.co. Your cooperation will be strongly appreciated and it is of great importance to the admission process of the candidate. The information registered in this format will be absolutely confidential and will remain in the School's Admissions Office."
            : 'Este formato debe ser diligenciado por el director, coordinador o psicólogo de la institución educativa donde el aspirante cursa sus estudios actuales, y devuelto en un sobre sellado a la Oficina de Admisiones del Colegio Real Royal School. Su colaboración es de gran importancia para el proceso de admisión del candidato y será considerada confidencialmente por el comité de evaluación.', 0, 'L');
    }

    private function drawCamposEncabezado(TCPDF $pdf, bool $en, array $datos, string $nombreInstitucion): void
    {
        $this->ensureSpacio($pdf, 28);
        $this->campoLinea($pdf, $en ? "Student's Name:" : 'Nombre del estudiante:', $datos['nombre_estudiante'] ?? '', self::MARGIN, self::CONTENT_W);
        $pdf->Ln(4);

        if ($en) {
            $this->campoLinea($pdf, 'Grade applying for:', $datos['grado_actual'] ?? '', self::MARGIN, self::CONTENT_W);
        } else {
            $mitad = self::CONTENT_W / 2 - 6;
            $y = $pdf->GetY();
            $this->campoLinea($pdf, 'Jardín/Colegio:', $nombreInstitucion, self::MARGIN, $mitad);
            $pdf->SetXY(self::MARGIN + $mitad + 12, $y);
            $this->campoLinea($pdf, 'Grado actual:', $datos['grado_actual'] ?? '', self::MARGIN + $mitad + 12, $mitad);
        }
    }

    private function campoLinea(TCPDF $pdf, string $label, string $valor, float $x, float $width): void
    {
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($x, $y);
        $labelW = $pdf->GetStringWidth($label) + 4;
        $pdf->Cell($labelW, 12, $label, 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell($width - $labelW, 12, $valor !== '' ? $valor : '-', 'B', 0, 'L');
        $pdf->SetXY($x, $y + 12);
    }

    private function drawPregunta(TCPDF $pdf, string $numero, string $label, string $valor): void
    {
        $this->ensureSpacio($pdf, 24);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell(20, 12, $numero, 0, 0, 'L');
        $pdf->SetXY(self::MARGIN + 20, $y);
        $pdf->MultiCell(self::CONTENT_W - 20, 12, $label, 0, 'L');

        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetX(self::MARGIN + 20);
        $pdf->MultiCell(self::CONTENT_W - 20, 12, $valor !== '' ? $valor : '-', 'B', 'L');
    }

    /** Columnas: pregunta | SI | NO | COMENTARIOS. */
    private const COL_PREGUNTA_W = 300.0;
    private const COL_CHECK_W = 30.0;

    private function drawTablaRespuestas(TCPDF $pdf, string $titulo, string $siLabel, string $noLabel, string $comentariosLabel, array $filas, array $respuestas, string $idioma): void
    {
        $colX = [
            self::MARGIN,
            self::MARGIN + self::COL_PREGUNTA_W,
            self::MARGIN + self::COL_PREGUNTA_W + self::COL_CHECK_W,
            self::MARGIN + self::COL_PREGUNTA_W + self::COL_CHECK_W * 2,
            self::RIGHT_X,
        ];

        $this->drawFilaEncabezadoTabla($pdf, $colX, $titulo, $siLabel, $noLabel, $comentariosLabel);

        $numero = 1;
        foreach ($filas as $fila) {
            if (($fila['soloEs'] ?? false) && $idioma !== 'es') {
                continue;
            }

            $label = $numero . '.' . $fila[$idioma];
            $numero++;

            $respuesta = $respuestas[$fila['key']] ?? null;

            $tipoFila = $fila['tipo'] ?? 'yesno';

            if ($tipoFila === 'terapia') {
                $lineasComentario = $this->lineasTerapia($respuesta, $idioma);
                $this->drawFilaTabla($pdf, $colX, $label, $respuesta['valor'] ?? null, $lineasComentario, $titulo, $siLabel, $noLabel, $comentariosLabel);
            } elseif ($tipoFila === 'texto') {
                $valor = is_string($respuesta) ? $respuesta : ($respuestas[$fila['key']] ?? '');
                $this->drawFilaTabla($pdf, $colX, $label, null, $valor !== '' ? [$valor] : [], $titulo, $siLabel, $noLabel, $comentariosLabel);
            } else {
                $comentarios = $respuesta['comentarios'] ?? '';
                $this->drawFilaTabla($pdf, $colX, $label, $respuesta['valor'] ?? null, $comentarios !== '' ? [$comentarios] : [], $titulo, $siLabel, $noLabel, $comentariosLabel);
            }
        }
    }

    /** @return string[] */
    private function lineasTerapia(?array $respuesta, string $idioma): array
    {
        if ($idioma !== 'es') {
            $nombres = $respuesta['nombre_terapeutas'] ?? '';

            return $nombres !== '' ? [$nombres] : [];
        }

        $lineas = [];
        if ($respuesta['fonoaudiologia'] ?? false) {
            $lineas[] = 'X Fonoaudiología';
        }
        if ($respuesta['terapia_ocupacional'] ?? false) {
            $lineas[] = 'X Terapia ocupacional';
        }
        if ($respuesta['psicologia'] ?? false) {
            $lineas[] = 'X Psicología';
        }
        if (($respuesta['nombre_terapeutas'] ?? '') !== '') {
            $lineas[] = $respuesta['nombre_terapeutas'];
        }

        return $lineas;
    }

    /** checkPageBreak es protected en TCPDF — este wrapper replica su chequeo (usado
     * desde fuera de la clase, al dibujar filas/celdas con coordenadas manuales en vez
     * de dejar que Cell/MultiCell paginen por sí solos) y agrega página si no cabe. */
    private function ensureSpacio(TCPDF $pdf, float $height): bool
    {
        $limite = $pdf->getPageHeight() - $pdf->getBreakMargin();
        if ($pdf->GetY() + $height > $limite) {
            $pdf->AddPage();

            return true;
        }

        return false;
    }

    private function drawFilaEncabezadoTabla(TCPDF $pdf, array $colX, string $titulo, string $siLabel, string $noLabel, string $comentariosLabel): void
    {
        $rowH = 20.0;
        $this->ensureSpacio($pdf, $rowH);
        $y = $pdf->GetY();

        $pdf->SetFillColorArray(self::NAVY);
        $pdf->Rect($colX[0], $y, $colX[4] - $colX[0], $rowH, 'F');

        $pdf->SetTextColorArray([255, 255, 255]);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($colX[0], $y);
        $pdf->Cell($colX[1] - $colX[0], $rowH, $titulo, 0, 0, 'C');
        $pdf->SetXY($colX[1], $y);
        $pdf->Cell($colX[2] - $colX[1], $rowH, $siLabel, 0, 0, 'C');
        $pdf->SetXY($colX[2], $y);
        $pdf->Cell($colX[3] - $colX[2], $rowH, $noLabel, 0, 0, 'C');
        $pdf->SetXY($colX[3], $y);
        $pdf->Cell($colX[4] - $colX[3], $rowH, $comentariosLabel, 0, 0, 'C');

        $pdf->SetTextColorArray([0, 0, 0]);
        $pdf->SetXY(self::MARGIN, $y + $rowH);
    }

    /** @param string[] $lineasComentario */
    private function drawFilaTabla(TCPDF $pdf, array $colX, string $label, ?string $valor, array $lineasComentario, string $titulo, string $siLabel, string $noLabel, string $comentariosLabel): void
    {
        $pdf->SetFont('helvetica', '', 8.5);
        $anchoPregunta = $colX[1] - $colX[0] - 6;
        $anchoComentario = $colX[4] - $colX[3] - 6;
        $textoComentario = implode("\n", $lineasComentario);

        // getStringHeight (no getNumLines * altura-fija) porque el alto real de una línea
        // envuelta puede caer justo entre dos múltiplos de la altura estimada — con maxh
        // ajustado al límite, MultiCell descarta la última línea entera en vez de recortarla.
        $alturaPregunta = $pdf->getStringHeight($anchoPregunta, $label);
        $alturaComentario = $textoComentario !== '' ? $pdf->getStringHeight($anchoComentario, $textoComentario) : 0.0;
        $rowH = max(18.0, $alturaPregunta, $alturaComentario) + 6;

        if ($this->ensureSpacio($pdf, $rowH)) {
            $this->drawFilaEncabezadoTabla($pdf, $colX, $titulo, $siLabel, $noLabel, $comentariosLabel);
        }
        $y = $pdf->GetY();

        $pdf->MultiCell($anchoPregunta, 10.5, $label, 0, 'L', false, 0, $colX[0] + 3, $y + 3, true, 0, false, true, $rowH - 6, 'T', false);

        $pdf->SetFont('helvetica', 'B', 9.5);
        if ($valor === 'si') {
            $pdf->SetXY($colX[1], $y + ($rowH - 12) / 2);
            $pdf->Cell($colX[2] - $colX[1], 12, 'X', 0, 0, 'C');
        } elseif ($valor === 'no') {
            $pdf->SetXY($colX[2], $y + ($rowH - 12) / 2);
            $pdf->Cell($colX[3] - $colX[2], 12, 'X', 0, 0, 'C');
        }

        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($anchoComentario, 10.5, $textoComentario, 0, 'L', false, 0, $colX[3] + 3, $y + 3, true, 0, false, true, $rowH - 6, 'T', false);

        $pdf->SetLineWidth(0.6);
        $pdf->SetDrawColorArray(self::GRID_GRAY);
        foreach ($colX as $x) {
            $pdf->Line($x, $y, $x, $y + $rowH);
        }
        $pdf->Line($colX[0], $y + $rowH, $colX[4], $y + $rowH);

        $pdf->SetXY(self::MARGIN, $y + $rowH);
    }

    private function drawFirmante(TCPDF $pdf, bool $en, array $firmante, string $nombreInstitucion): void
    {
        $labelFirma = $en ? 'Signature:' : 'Firma:';

        $campos = $en
            ? [
                ['Name:', $firmante['nombre'] ?? ''],
                [$labelFirma, ''],
                ['Position:', $firmante['cargo'] ?? ''],
                ['School:', $nombreInstitucion],
                ['Telephone:', $firmante['telefono'] ?? ''],
                ['E mail:', $firmante['correo'] ?? ''],
                ['Date:', $firmante['fecha'] ?? ''],
            ]
            : [
                ['Nombre:', $firmante['nombre'] ?? ''],
                [$labelFirma, ''],
                ['Cargo:', $firmante['cargo'] ?? ''],
                ['Teléfono / Celular:', $firmante['telefono'] ?? ''],
                ['Correo electrónico:', $firmante['correo'] ?? ''],
                ['Fecha:', $firmante['fecha'] ?? ''],
            ];

        $labelW = 120.0;
        foreach ($campos as [$label, $valor]) {
            if ($label === $labelFirma) {
                $this->drawFilaFirma($pdf, $label, $labelW, (string) ($firmante['firma'] ?? ''));
                continue;
            }

            $this->ensureSpacio($pdf, 16);
            $y = $pdf->GetY();
            $pdf->SetFont('helvetica', '', 9.5);
            $pdf->SetXY(self::MARGIN, $y);
            $pdf->Cell($labelW, 14, $label, 0, 0, 'L');
            $pdf->Cell(self::CONTENT_W - $labelW, 14, $valor !== '' ? $valor : '-', 'B', 0, 'L');
            $pdf->SetXY(self::MARGIN, $y + 16);
        }
    }

    /** Dibuja la fila "Firma:" — si la institución subió una imagen de la firma (data URL
     * base64, ver FirmanteFields.parts.tsx en el frontend) la incrusta sobre la línea; si
     * no, deja la línea en blanco como el resto de campos. */
    private function drawFilaFirma(TCPDF $pdf, string $label, float $labelW, string $firmaDataUrl): void
    {
        $firma = $this->decodeFirmaTemp($firmaDataUrl);
        $rowH = 16.0;
        $imgW = 0.0;
        $imgH = 0.0;

        if ($firma) {
            $tamano = @getimagesize($firma['path']);
            if ($tamano && $tamano[0] > 0 && $tamano[1] > 0) {
                $maxW = 150.0;
                $maxH = 36.0;
                $escala = min($maxW / $tamano[0], $maxH / $tamano[1], 1.0);
                $imgW = $tamano[0] * $escala;
                $imgH = $tamano[1] * $escala;
                $rowH = max($rowH, $imgH + 4);
            }
        }

        $this->ensureSpacio($pdf, $rowH);
        $y = $pdf->GetY();

        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell($labelW, $rowH, $label, 0, 0, 'L');

        if ($firma && $imgH > 0) {
            $pdf->Image($firma['path'], self::MARGIN + $labelW, $y, $imgW, $imgH, $firma['type']);
            $pdf->Line(self::MARGIN + $labelW, $y + $rowH, self::RIGHT_X, $y + $rowH);
            @unlink($firma['path']);
        } else {
            $pdf->Cell(self::CONTENT_W - $labelW, $rowH, '', 'B', 0, 'L');
        }

        $pdf->SetXY(self::MARGIN, $y + $rowH);
    }

    /** @return array{path: string, type: string}|null */
    private function decodeFirmaTemp(string $dataUrl): ?array
    {
        if (! preg_match('#^data:image/(png|jpe?g);base64,(.+)$#i', $dataUrl, $m)) {
            return null;
        }

        $tipo = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
        $binario = base64_decode($m[2], true);
        if ($binario === false || $binario === '') {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'firma') . '.' . $tipo;
        file_put_contents($path, $binario);

        return ['path' => $path, 'type' => strtoupper($tipo)];
    }

    private function drawFooter(TCPDF $pdf): void
    {
        $this->ensureSpacio($pdf, 55);
        $y = $pdf->GetY() + 10;

        $pdf->SetLineWidth(1.4);
        $pdf->SetDrawColorArray(self::RED);
        $pdf->Line(self::MARGIN, $y, self::RIGHT_X, $y);
        $pdf->SetLineWidth(1.0);
        $pdf->SetDrawColorArray(self::NAVY);
        $pdf->Line(self::MARGIN, $y + 3, self::RIGHT_X, $y + 3);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColorArray([80, 80, 80]);
        $pdf->SetXY(self::MARGIN, $y + 8);
        $pdf->Cell(self::CONTENT_W, 10, 'Autopista Vía al Mar · Poste 66', 0, 2, 'R');
        $pdf->SetX(self::MARGIN);
        $pdf->Cell(self::CONTENT_W, 10, 'PBX: (605) 322 5086 / (317) 439-7707', 0, 2, 'R');
        $pdf->SetX(self::MARGIN);
        $pdf->Cell(self::CONTENT_W, 10, 'Barranquilla · Colombia', 0, 2, 'R');
        $pdf->SetX(self::MARGIN);
        $pdf->Cell(self::CONTENT_W, 10, 'www.realroyalschool.edu.co', 0, 0, 'R');
        $pdf->SetTextColorArray([0, 0, 0]);
    }

    // ── Formato Play and Learn — recreación de CARTA RECOMENDACION P AND L.pdf ─────

    private function generatePlayAndLearn(array $datos, string $nombreInstitucion): string
    {
        $pyl = $datos['play_and_learn'] ?? [];

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OMNIA');
        $pdf->SetTitle('Carta de Recomendación — Play and Learn');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->SetAutoPageBreak(true, 55);
        $pdf->AddPage();

        $this->drawEncabezado($pdf, 'ADMISIONES', 'CARTA DE RECOMENDACION ASPIRANTES PROVENIENTES DE PLAY AND LEARN', '25-08-2021');

        $pdf->SetY(130);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->MultiCell(self::CONTENT_W, 12.5, 'Este formato debe ser diligenciado por Play and Learn, y devuelto en un sobre sellado a la Oficina de Admisiones. La información suministrada en esta Carta de Recomendación será considerada confidencialmente por el Comité de Admisión del Colegio Real.', 0, 'L');

        $pdf->Ln(10);
        $this->campoLinea($pdf, 'Nombre del estudiante:', $datos['nombre_estudiante'] ?? '', self::MARGIN, self::CONTENT_W);
        $pdf->Ln(4);
        $this->drawFechaIngresoPyL($pdf, $pyl);
        $pdf->Ln(4);
        $this->drawGradosCursadosPyL($pdf, $pyl, $datos['grado_actual'] ?? '');

        $pdf->Ln(8);
        $this->drawPregunta($pdf, '1.', '¿Cuáles considera son las fortalezas del estudiante?', $datos['fortalezas'] ?? '');
        $pdf->Ln(4);
        $this->drawPregunta($pdf, '2.', '¿Cuáles considera son las debilidades del estudiante?', $datos['debilidades'] ?? '');

        $pdf->Ln(10);
        $this->drawTablaRespuestas($pdf, 'INFORMACIÓN DEL ESTUDIANTE', 'SI', 'NO', 'COMENTARIOS', self::FILAS_ESTUDIANTE, $datos['estudiante'] ?? [], 'es');

        // Igual que en Coordinador/Psicólogo: "Información de los padres" y todo lo que
        // le sigue arrancan juntos en una página nueva.
        $pdf->AddPage();
        $this->drawTablaRespuestas($pdf, 'INFORMACIÓN DE LOS PADRES', 'SI', 'NO', 'COMENTARIOS', self::FILAS_PADRES, $datos['padres'] ?? [], 'es');

        $pdf->Ln(14);
        $this->drawSiNoInline($pdf, 'P&L recomienda a la familia para formar parte de la Comunidad Real?', $pyl['recomienda_comunidad_real'] ?? null);

        $pdf->Ln(10);
        $this->ensureSpacio($pdf, 16);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY(self::MARGIN, $pdf->GetY());
        $pdf->Cell(self::CONTENT_W, 12, 'P&L recomienda ingreso a:', 0, 0, 'L');
        $pdf->SetXY(self::MARGIN, $pdf->GetY() + 16);
        $this->drawIngresoLineaPyL($pdf, 'Stem', ! empty($pyl['recomienda_stem']), $pyl['recomienda_stem_anio'] ?? '');
        $pdf->Ln(4);
        $this->drawIngresoLineaPyL($pdf, 'Pre Kinder', ! empty($pyl['recomienda_pre_kinder']), $pyl['recomienda_pre_kinder_anio'] ?? '');

        $pdf->Ln(12);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->MultiCell(self::CONTENT_W, 12, 'Favor incluir comentario adicional que considere importante para hacer una evaluación detallada del estudiante, como candidato para admisión al Colegio Real Royal School.', 0, 'L');
        $pdf->Ln(3);
        $pdf->MultiCell(self::CONTENT_W, 12, ($datos['comentario_adicional'] ?? '') !== '' ? $datos['comentario_adicional'] : '-', 0, 'L');

        $pdf->Ln(14);
        $this->drawFirmante($pdf, false, $datos['firmante'] ?? [], $nombreInstitucion);

        $this->drawFooter($pdf);

        return $pdf->Output('', 'S');
    }

    private function drawFechaIngresoPyL(TCPDF $pdf, array $pyl): void
    {
        $this->ensureSpacio($pdf, 16);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $label = 'Fecha de ingreso a Play and Learn:';
        $labelW = $pdf->GetStringWidth($label) + 8;
        $pdf->Cell($labelW, 12, $label, 0, 0, 'L');

        $x = self::MARGIN + $labelW;
        $pdf->SetXY($x, $y);
        $mesLabelW = $pdf->GetStringWidth('Mes') + 6;
        $pdf->Cell($mesLabelW, 12, 'Mes', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell(100, 12, ($pyl['fecha_ingreso_mes'] ?? '') !== '' ? $pyl['fecha_ingreso_mes'] : '-', 'B', 0, 'L');

        $x2 = $x + $mesLabelW + 100 + 20;
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($x2, $y);
        $anioLabelW = $pdf->GetStringWidth('Año') + 6;
        $pdf->Cell($anioLabelW, 12, 'Año', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell(self::RIGHT_X - ($x2 + $anioLabelW), 12, ($pyl['fecha_ingreso_anio'] ?? '') !== '' ? $pyl['fecha_ingreso_anio'] : '-', 'B', 0, 'L');

        $pdf->SetXY(self::MARGIN, $y + 12);
    }

    private function drawGradosCursadosPyL(TCPDF $pdf, array $pyl, string $gradoActual): void
    {
        $this->ensureSpacio($pdf, 16);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $label = 'Grados Cursados en Play and Learn:';
        $labelW = $pdf->GetStringWidth($label) + 8;
        $pdf->Cell($labelW, 12, $label, 0, 0, 'L');

        $grados = $pyl['grados_cursados'] ?? [];
        $x = self::MARGIN + $labelW;
        foreach ([['n', 'N'], ['m', 'M'], ['st', 'ST']] as [$key, $texto]) {
            $pdf->SetFont('helvetica', 'B', 9.5);
            $pdf->SetXY($x, $y + 1);
            $textoW = $pdf->GetStringWidth($texto);
            $pdf->Cell($textoW + 4, 11, $texto, 0, 0, 'L');
            $x += $textoW + 6;
            $pdf->Rect($x, $y + 1, 11, 11, 'D');
            if (! empty($grados[$key])) {
                $pdf->SetXY($x, $y + 1);
                $pdf->Cell(11, 11, 'X', 0, 0, 'C');
            }
            $x += 24;
        }

        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($x, $y);
        $gaLabel = 'Grado Actual:';
        $gaLabelW = $pdf->GetStringWidth($gaLabel) + 6;
        $pdf->Cell($gaLabelW, 12, $gaLabel, 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell(self::RIGHT_X - ($x + $gaLabelW), 12, $gradoActual !== '' ? $gradoActual : '-', 'B', 0, 'L');

        $pdf->SetXY(self::MARGIN, $y + 12);
    }

    private function drawSiNoInline(TCPDF $pdf, string $label, ?string $valor): void
    {
        $this->ensureSpacio($pdf, 16);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell(self::CONTENT_W - 130, 12, $label, 0, 0, 'L');

        $x = self::RIGHT_X - 120;
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell(14, 12, $valor === 'si' ? 'X' : '', 1, 0, 'C');
        $pdf->SetXY($x + 18, $y);
        $pdf->Cell(26, 12, 'SI', 0, 0, 'L');

        $x2 = $x + 60;
        $pdf->SetXY($x2, $y);
        $pdf->Cell(14, 12, $valor === 'no' ? 'X' : '', 1, 0, 'C');
        $pdf->SetXY($x2 + 18, $y);
        $pdf->Cell(26, 12, 'NO', 0, 0, 'L');

        $pdf->SetXY(self::MARGIN, $y + 16);
    }

    private function drawIngresoLineaPyL(TCPDF $pdf, string $label, bool $marcado, string $anio): void
    {
        $this->ensureSpacio($pdf, 16);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell(14, 12, $marcado ? 'X' : '', 1, 0, 'C');
        $pdf->SetXY(self::MARGIN + 20, $y);
        $labelW = $pdf->GetStringWidth($label) + 6;
        $pdf->Cell($labelW, 12, $label, 0, 0, 'L');

        $x = self::MARGIN + 20 + $labelW + 20;
        $pdf->SetXY($x, $y);
        $aeLabel = 'Año Escolar:';
        $aeW = $pdf->GetStringWidth($aeLabel) + 6;
        $pdf->Cell($aeW, 12, $aeLabel, 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell(self::RIGHT_X - ($x + $aeW), 12, $anio !== '' ? $anio : '-', 'B', 0, 'L');

        $pdf->SetXY(self::MARGIN, $y + 12);
    }
}

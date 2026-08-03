<?php

namespace App\Pdf\Biblioteca;

use TCPDF;

/**
 * Recreación fiel del documento "PAZ Y SALVO BIBLIOTECA" usando TCPDF
 * (la misma librería con la que fue generado el PDF original).
 *
 * Las coordenadas fueron extraídas midiendo el PDF original (tamaño A4:
 * 595.28 x 841.89 pt) por lo que el resultado reproduce el mismo layout,
 * tipografías y tamaños. Como TCPDF usa por defecto origen arriba-izquierda
 * (igual que las mediciones "top" del PDF original), las coordenadas se
 * usan tal cual, sin necesidad de invertir el eje Y.
 */
class PazYSalvoPdfService
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;

    private const TABLE_LEFT = 31.2;
    private const TABLE_RIGHT = 556.2;

    /** Límites X de las 8 columnas de la tabla (9 valores = 8 columnas) — paz_y_salvo / listado (libros) */
    private const COL_X_LIBROS = [31.2, 96.8, 162.4, 228.1, 293.7, 359.3, 425.0, 490.6, 556.2];

    private const COLUMNS_LIBROS = [
        ['key' => 'no_prestamo',       'header' => ['No.', 'PRESTAMO']],
        ['key' => 'libro',             'header' => ['LIBRO']],
        ['key' => 'num_ejemplar',      'header' => ['#EJEMPLAR']],
        ['key' => 'categoria',         'header' => ['CATEGORÍA']],
        ['key' => 'subcategoria',      'header' => ['SUBCATEGO', 'RÍA']],
        ['key' => 'fecha_prestamo',    'header' => ['FECHA DE', 'PRESTAMO']],
        ['key' => 'fecha_devolucion',  'header' => ['FECHA', 'DEVOLUCIÓN']],
        ['key' => 'estado',            'header' => ['ESTADO']],
    ];

    /** 6 columnas para listado_paquetes (sin categoría/subcategoría) — espaciadas uniformemente en el mismo ancho de tabla. */
    private const COLUMNS_PAQUETES = [
        ['key' => 'no_prestamo',       'header' => ['No.', 'PRESTAMO']],
        ['key' => 'paquete',           'header' => ['PAQUETE']],
        ['key' => 'codigo',            'header' => ['#CÓDIGO']],
        ['key' => 'fecha_prestamo',    'header' => ['FECHA', 'PRESTAMO']],
        ['key' => 'fecha_devolucion',  'header' => ['FECHA', 'DEVOLUCION']],
        ['key' => 'estado',            'header' => ['ESTADO']],
    ];

    private function resolveColumnas(string $tipo): array
    {
        return $tipo === 'listado_paquetes' ? self::COLUMNS_PAQUETES : self::COLUMNS_LIBROS;
    }

    /** COL_X_LIBROS ya viene medido a mano; para paquetes (menos columnas) se reparte el mismo ancho en partes iguales. */
    private function resolveColX(string $tipo): array
    {
        if ($tipo !== 'listado_paquetes') {
            return self::COL_X_LIBROS;
        }

        $numColumnas = count(self::COLUMNS_PAQUETES);
        $ancho = (self::TABLE_RIGHT - self::TABLE_LEFT) / $numColumnas;

        return array_map(
            fn ($i) => self::TABLE_LEFT + $i * $ancho,
            range(0, $numColumnas)
        );
    }

    private string $logoPath;
    private string $selloPath;

    public function __construct(?string $logoPath = null, ?string $selloPath = null)
    {
        $this->logoPath = $logoPath ?? storage_path('app/public/images/paz&salvo/logo.webp');
        $this->selloPath = $selloPath ?? storage_path('app/public/images/paz&salvo/sello.webp');
    }

    /**
     * TCPDF no soporta WEBP nativamente: si la imagen viene en ese formato la
     * convierte a un PNG temporal (vía GD) y devuelve esa ruta.
     */
    private function resolveImage(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'webp') {
            return $path;
        }

        $imagen = @imagecreatefromwebp($path);
        if (!$imagen) {
            return null;
        }

        $temporal = tempnam(sys_get_temp_dir(), 'pyspdf') . '.png';
        imagepng($imagen, $temporal);
        imagedestroy($imagen);

        return $temporal;
    }

    /**
     * Genera el PDF y devuelve el contenido binario listo para descargar o guardar.
     *
     * @param array{
     *   tipo?: 'paz_y_salvo'|'listado',
     *   institucion: string,
     *   nombre_trabajador: string,
     *   numero_documento: string,
     *   prestamos?: array<int, array<string, string>>,
     *   nota?: string,
     *   numero_pagina?: int,
     *   sin_pendientes?: bool
     * } $data
     */
    public function generate(array $data): string
    {
        $tipo = $data['tipo'] ?? 'paz_y_salvo';
        $esListado = in_array($tipo, ['listado', 'listado_paquetes'], true);
        $columnas = $this->resolveColumnas($tipo);
        $colX = $this->resolveColX($tipo);

        $pdf = new TCPDF('P', 'pt', [self::PAGE_W, self::PAGE_H], true, 'UTF-8', false);
        $pdf->SetCreator('Paz y Salvo Generator (TCPDF / Laravel)');
        $pdf->SetAuthor($data['institucion'] ?? 'Institución');
        $pdf->SetTitle(match ($tipo) {
            'listado' => 'Listado de Prestamos Biblioteca',
            'listado_paquetes' => 'Listado de Prestamos de Paquetes Biblioteca',
            default => 'Paz y Salvo Biblioteca',
        });
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->AddPage();

        $numeroPagina = $data['numero_pagina'] ?? 1;

        $this->drawLogo($pdf);
        $this->drawTitulo($pdf, $tipo);
        $this->drawParrafo($pdf, $data, $tipo);
        $tableBottom = $this->drawTabla($pdf, $data['prestamos'] ?? [], $tipo, $numeroPagina, $columnas, $colX);

        // Posiciones "de diseño" (medidas del PDF original); si la tabla las
        // supera, el contenido se corre debajo de ella en vez de solaparse.
        $y = max($tableBottom + 12, $esListado ? 413.5 : 328.5);

        if (!$esListado) {
            $y = $this->asegurarEspacio($pdf, $y, 14, $numeroPagina);
            $this->drawNota($pdf, $data['nota'] ?? null, $y);
            $y += 85; // gap nota -> firma medido en el PDF original (413.5 - 328.5)
        }

        $y = $this->asegurarEspacio($pdf, $y, 40, $numeroPagina);
        $this->drawFirma($pdf, $y);
        $firmaY = $y;

        if (!$esListado && ($data['sin_pendientes'] ?? false)) {
            $selloY = $this->asegurarEspacio($pdf, $firmaY + 10.1, 113.4, $numeroPagina);
            $this->drawSello($pdf, $selloY);
        }

        $this->drawFooter($pdf, $numeroPagina);

        return $pdf->Output('', 'S');
    }

    /**
     * Si dibujar un bloque de $altura en $y se saldría de la página, cierra
     * la página actual (con su footer) y abre una nueva, devolviendo el Y
     * disponible en la página resultante.
     */
    private function asegurarEspacio(TCPDF $pdf, float $y, float $altura, int &$numeroPagina): float
    {
        $limiteInferior = self::PAGE_H - 50;

        if ($y + $altura <= $limiteInferior) {
            return $y;
        }

        $this->drawFooter($pdf, $numeroPagina);
        $pdf->AddPage();
        $numeroPagina++;

        return 40.0;
    }

    private function drawLogo(TCPDF $pdf): void
    {
        $width = 141.7;
        $height = 56.7;
        $x = (self::PAGE_W - $width) / 2;
        $y = 28.4;

        $logo = $this->resolveImage($this->logoPath);
        if ($logo) {
            $pdf->Image($logo, $x, $y, $width, $height, 'PNG');
        }
    }

    private function drawTitulo(TCPDF $pdf, string $tipo): void
    {
        $texto = match ($tipo) {
            'listado' => 'LISTADO DE PRESTAMOS BIBLIOTECA',
            'listado_paquetes' => 'LISTADO DE PRESTAMOS (PAQUETES) BIBLIOTECA',
            default => 'PAZ Y SALVO BIBLIOTECA',
        };

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(0, 101.5);
        $pdf->Cell(self::PAGE_W, 14, $texto, 0, 0, 'C');
    }

    private function drawParrafo(TCPDF $pdf, array $data, string $tipo): void
    {
        $institucion = (string) ($data['institucion'] ?? '');
        $nombre = (string) ($data['nombre_trabajador'] ?? '');
        $documento = (string) ($data['numero_documento'] ?? '');
        $esListado = in_array($tipo, ['listado', 'listado_paquetes'], true);

        if ($tipo === 'listado_paquetes') {
            $curso = (string) ($data['curso'] ?? '');
            $segments = [
                ['El ', false],
                ["{$institucion} ", true],
                ['certifica que el Estudiante ', false],
                [" {$nombre} , ", true],
                ...($curso !== '' ? [['perteneciente al curso ', false], ["{$curso} , ", true]] : []),
                ['identificado con número de documento ', false],
                ["{$documento} ", true],
                ['tiene en posesión los siguientes paquetes.', false],
            ];
        } else {
            // Segmentos [texto, negrita]
            $segments = [
                ['El ', false],
                ["{$institucion} ", true],
                ['certifica que el trabajador ', false],
                $esListado ? [" {$nombre} , ", true] : [" {$nombre} ,, ", true],
                ['identificado con número de documento ', false],
                ["{$documento} ", true],
                $esListado
                    ? ['tiene en posesión los siguientes libros.', false]
                    // 'listado' (solo libros) sigue diciendo "libros"; solo el paz_y_salvo real
                    // menciona ambos porque generarPazYSalvoPdf ya fusiona libros y paquetes.
                    : ['se encuentra a paz y salvo por concepto de prestamo de libros y paquetes.', false],
            ];
        }

        $this->drawMixedParagraph($pdf, $segments, 28.4, 154.6, self::PAGE_W - 28.4 * 2, 10, 12.6);
    }

    /**
     * Dibuja un párrafo con segmentos normales/negrita, con wrap automático,
     * igual que la versión de pdf-lib (evita depender de writeHTMLCell).
     *
     * @param array<int, array{0: string, 1: bool}> $segments
     */
    private function drawMixedParagraph(TCPDF $pdf, array $segments, float $x, float $y, float $maxWidth, float $size, float $lineHeight): void
    {
        $tokens = [];
        foreach ($segments as [$text, $bold]) {
            foreach (preg_split('/\s+/', trim($text)) as $word) {
                if ($word !== '') {
                    $tokens[] = [$word, $bold];
                }
            }
        }

        $pdf->SetFont('helvetica', '', $size);
        $spaceWidth = $pdf->GetStringWidth(' ');

        $line = [];
        $lineWidth = 0.0;
        $cursorY = $y;

        $flush = function () use (&$line, &$lineWidth, &$cursorY, $pdf, $x, $lineHeight) {
            if (empty($line)) {
                return;
            }
            $cursorX = $x;
            foreach ($line as [$word, $bold]) {
                $pdf->SetFont('helvetica', $bold ? 'B' : '', 10);
                $pdf->SetXY($cursorX, $cursorY);
                $pdf->Cell($pdf->GetStringWidth($word) + 1, $lineHeight, $word, 0, 0, 'L');
                $cursorX += $pdf->GetStringWidth($word) + $pdf->GetStringWidth(' ');
            }
            $cursorY += $lineHeight;
            $line = [];
            $lineWidth = 0.0;
        };

        foreach ($tokens as [$word, $bold]) {
            $pdf->SetFont('helvetica', $bold ? 'B' : '', 10);
            $wWidth = $pdf->GetStringWidth($word);
            $added = (count($line) > 0 ? $spaceWidth : 0) + $wWidth;
            if (($lineWidth + $added) > $maxWidth && count($line) > 0) {
                $flush();
                $lineWidth = $wWidth;
                $line[] = [$word, $bold];
            } else {
                $lineWidth += $added;
                $line[] = [$word, $bold];
            }
        }
        $flush();
    }

    /**
     * Dibuja la tabla de préstamos, repitiendo título + encabezados en una
     * página nueva cuando las filas no caben en la actual. Devuelve el Y
     * donde termina la tabla (en la última página que ocupó).
     */
    private function drawTabla(TCPDF $pdf, array $prestamos, string $tipo, int &$numeroPagina, array $columnas, array $colX): float
    {
        $titleRowH = 16.6;
        $headerRowH = 27.3;
        $tableTop = 217.0;
        $headerFontSize = 8.5;
        $cellFontSize = 8;
        $cellLineHeight = 9.5;
        $limiteInferior = self::PAGE_H - 50;

        $tituloTabla = match ($tipo) {
            'listado' => 'LISTADO DE PRESTAMOS',
            'listado_paquetes' => 'LISTADO DE PRESTAMOS (PAQUETES)',
            default => 'HISTORIAL DE PRESTAMOS',
        };
        $mensajeVacio = match ($tipo) {
            'listado_paquetes' => 'No hay prestamos de paquetes registrados',
            'listado' => 'No hay prestamos registrados',
            default => 'No hay prestamos pendientes',
        };

        // --- Calcula altura de cada fila de datos (o la fila "sin préstamos") ---
        $rowHeights = [];

        if (empty($prestamos)) {
            $rowHeights[] = 16.6;
        } else {
            $pdf->SetFont('helvetica', '', $cellFontSize);
            foreach ($prestamos as $row) {
                $lineCounts = [];
                foreach ($columnas as $i => $col) {
                    $colWidth = $colX[$i + 1] - $colX[$i] - 6;
                    $text = (string) ($row[$col['key']] ?? '');
                    $numLines = max(1, $pdf->getNumLines($text, $colWidth));
                    $lineCounts[] = $numLines;
                }
                $maxLines = max($lineCounts);
                $rowHeights[] = max(16.6, $maxLines * $cellLineHeight + 6);
            }
        }

        // Dibuja título + encabezados de columna en $top y devuelve [headerRowTop, bodyTop]
        $dibujarCabecera = function (float $top) use ($pdf, $titleRowH, $headerRowH, $headerFontSize, $tituloTabla, $columnas, $colX): array {
            $headerRowTop = $top + $titleRowH;
            $bodyTop = $headerRowTop + $headerRowH;

            $pdf->SetFont('helvetica', 'B', $headerFontSize);
            $pdf->SetXY(self::TABLE_LEFT, $top + 3);
            $pdf->Cell(self::TABLE_RIGHT - self::TABLE_LEFT, $titleRowH - 3, $tituloTabla, 0, 0, 'C');

            foreach ($columnas as $i => $col) {
                $x0 = $colX[$i];
                $w = $colX[$i + 1] - $x0;
                foreach ($col['header'] as $li => $linea) {
                    $pdf->SetXY($x0, $headerRowTop + 3.5 + $li * 10.7);
                    $pdf->Cell($w, 10, $linea, 0, 0, 'C');
                }
            }

            return [$headerRowTop, $bodyTop];
        };

        [$headerRowTop, $bodyTop] = $dibujarCabecera($tableTop);
        $paginaTop = $tableTop;
        $paginaHeaderRowTop = $headerRowTop;
        $rowTop = $bodyTop;
        $rowYsPagina = [$paginaTop, $paginaHeaderRowTop, $bodyTop];

        $pdf->SetFont('helvetica', '', $cellFontSize);

        if (empty($prestamos)) {
            $pdf->SetXY(self::TABLE_LEFT, $bodyTop + 3.5);
            $pdf->Cell(self::TABLE_RIGHT - self::TABLE_LEFT, 10, $mensajeVacio, 0, 0, 'C');
            $rowTop += $rowHeights[0];
            $rowYsPagina[] = $rowTop;
        } else {
            foreach ($prestamos as $rIdx => $row) {
                $rowH = $rowHeights[$rIdx];

                if ($rowTop + $rowH > $limiteInferior) {
                    $this->drawTablaGrid($pdf, $paginaTop, $paginaHeaderRowTop, $rowTop, $rowYsPagina, $colX);
                    $this->drawFooter($pdf, $numeroPagina);
                    $pdf->AddPage();
                    $numeroPagina++;

                    [$headerRowTop, $bodyTop] = $dibujarCabecera($tableTop);
                    $paginaTop = $tableTop;
                    $paginaHeaderRowTop = $headerRowTop;
                    $rowTop = $bodyTop;
                    $rowYsPagina = [$paginaTop, $paginaHeaderRowTop, $bodyTop];
                    $pdf->SetFont('helvetica', '', $cellFontSize);
                }

                foreach ($columnas as $i => $col) {
                    $x0 = $colX[$i] + 3;
                    $colWidth = $colX[$i + 1] - $colX[$i] - 6;
                    $text = (string) ($row[$col['key']] ?? '');
                    $pdf->MultiCell(
                        $colWidth,
                        $cellLineHeight,
                        $text,
                        0,
                        'L',
                        false,
                        0,
                        $x0,
                        $rowTop + 3,
                        true,
                        0,
                        false,
                        true,
                        0,
                        'T',
                        false
                    );
                }

                $rowTop += $rowH;
                $rowYsPagina[] = $rowTop;
            }
        }

        $this->drawTablaGrid($pdf, $paginaTop, $paginaHeaderRowTop, $rowTop, $rowYsPagina, $colX);

        return $rowTop;
    }

    /**
     * Dibuja las líneas y el borde de la tabla para el segmento que cae en
     * una sola página (líneas horizontales de $rowYs, verticales entre
     * $headerRowTop y $tableBottom, y el borde exterior desde $tableTop).
     */
    private function drawTablaGrid(TCPDF $pdf, float $tableTop, float $headerRowTop, float $tableBottom, array $rowYs, array $colX): void
    {
        $pdf->SetLineWidth(0.75);
        $pdf->SetDrawColor(0, 0, 0);

        foreach ($rowYs as $y) {
            $pdf->Line(self::TABLE_LEFT, $y, self::TABLE_RIGHT, $y);
        }

        foreach ($colX as $x) {
            $pdf->Line($x, $headerRowTop, $x, $tableBottom);
        }

        $pdf->Rect(self::TABLE_LEFT, $tableTop, self::TABLE_RIGHT - self::TABLE_LEFT, $tableBottom - $tableTop, 'D');
    }

    private function drawNota(TCPDF $pdf, ?string $nota, float $y): void
    {
        $texto = $nota ?? 'Nota: Este documento es válido como paz y salvo únicamente con firma y sello de biblioteca.';
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(34.0, $y);
        $pdf->Cell(self::PAGE_W - 34.0 * 2, 10, $texto, 0, 0, 'L');
    }

    private function drawFirma(TCPDF $pdf, float $y): void
    {
        $lineText = '__________________________';
        $x = 144.6;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($x, $y);
        $pdf->Cell($pdf->GetStringWidth($lineText), 10, $lineText, 0, 0, 'L');

        $lineWidth = $pdf->GetStringWidth($lineText);
        $label = 'Nombre Trabajador';
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($x, $y + 14);
        $pdf->Cell($lineWidth, 10, $label, 0, 0, 'C');
    }

    private function drawSello(TCPDF $pdf, float $y): void
    {
        $width = 170.1;
        $height = 113.4;
        $x = 368.5;

        $sello = $this->resolveImage($this->selloPath);
        if ($sello) {
            $pdf->Image($sello, $x, $y, $width, $height, 'PNG');
        }
    }

    private function drawFooter(TCPDF $pdf, int $numeroPagina): void
    {
        $texto = "Pagina {$numeroPagina}";
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(102, 102, 102);
        $pdf->SetXY(0, 805.5);
        $pdf->Cell(self::PAGE_W, 14, $texto, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }
}

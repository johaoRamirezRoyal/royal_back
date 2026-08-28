<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\CargaAcademica;
use App\Models\GestionAcademica\FranjaHoraria;
use App\Models\Usuarios\Usuario;
use App\Services\branding\MarcaDominioService;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera el .xlsx de "Mi horario" en el servidor — antes esta lógica vivía enteramente en
 * el frontend (MyScheduleSelection/helpers/{horarioGrid.helpers,exportMiHorarioExcel}.ts) y
 * "exportar todos los docentes" hacía N peticiones desde el navegador (una por docente) más
 * el armado del .xlsx con ExcelJS en el cliente. Acá se arma todo de una vez y el frontend
 * solo dispara la descarga del binario ya listo.
 *
 * Cada día tiene su PROPIA columna "Hora" (no una columna "Hora" única compartida al
 * principio de la tabla): las franjas de un esquema no tienen por qué alinearse entre
 * días, y un docente puede además dictar en más de un esquema/nivel a la vez, cada uno con
 * su propia numeración horaria. La grilla se arma a partir de TODAS las franjas de los
 * esquemas de este docente (no solo las que tienen algo agendado) para que se vea la
 * jornada completa, con las franjas vacías en blanco.
 */
class HorarioExcelService
{
    private const FONT_NAME = 'Arial';

    private const TIPO_LABEL = [
        'CLASE' => 'Clase',
        'PLANEACION' => 'Planeación',
        'REUNION' => 'Reunión',
        'CLUB' => 'Club',
        'LIBRE' => 'Libre',
        'RECESO' => 'Receso',
        'ALMUERZO' => 'Almuerzo',
    ];

    public function __construct(
        private HorarioClaseService $horarioClaseService,
        private MarcaDominioService $marcaDominioService,
    ) {}

    /**
     * Un único docente — usado tanto por "Mi horario" (el propio docente autenticado) como
     * por el admin desde Configuración académica > Horario, sobre el docente seleccionado.
     */
    public function exportarDocente(int $idDocente): array
    {
        try {
            $docente = Usuario::select('id_user', 'nombre', 'apellido', 'correo')->find($idDocente);

            if (!$docente) {
                return ['error' => true, 'message' => 'El docente no existe.', 'data' => []];
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $nombre = trim("{$docente->nombre} {$docente->apellido}");
            $logoPath = $this->marcaDominioService->resolverRutaLocalPorCorreo($docente->correo);

            if (!$this->agregarHoja($spreadsheet, $idDocente, $nombre, 'Mi horario', $logoPath)) {
                return ['error' => true, 'message' => 'Este docente no tiene ningún esquema de horario asignado.', 'data' => []];
            }

            return [
                'error' => false,
                'message' => 'Horario exportado correctamente.',
                'data' => [
                    'contenido' => $this->serializar($spreadsheet),
                    'nombre_archivo' => 'Mi_horario_'.str_replace(' ', '_', $nombre).'.xlsx',
                ],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => 'Error en el servidor al exportar el horario.', 'data' => []];
        }
    }

    /**
     * Un único .xlsx con una hoja por docente — solo los que tengan al menos una clase real
     * (con carga académica) asignada, en vez de exigir exportar docente por docente.
     */
    public function exportarTodosLosDocentes(): array
    {
        try {
            $idsDocentes = CargaAcademica::query()
                ->join('academico_horario_clase', 'academico_horario_clase.id_carga_academica', '=', 'academico_carga_academica.id')
                ->join('academico_docente_asignatura', 'academico_docente_asignatura.id', '=', 'academico_carga_academica.id_docente_asignatura')
                ->distinct()
                ->pluck('academico_docente_asignatura.id_docente');

            if ($idsDocentes->isEmpty()) {
                return ['error' => true, 'message' => 'Ningún docente tiene bloques de horario.', 'data' => []];
            }

            $docentes = Usuario::select('id_user', 'nombre', 'apellido', 'correo')
                ->whereIn('id_user', $idsDocentes)
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get();

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $usados = [];
            $count = 0;

            foreach ($docentes as $docente) {
                $nombre = trim("{$docente->nombre} {$docente->apellido}");
                $sheetName = $this->nombreHojaUnico($nombre, $usados);
                $logoPath = $this->marcaDominioService->resolverRutaLocalPorCorreo($docente->correo);

                if ($this->agregarHoja($spreadsheet, $docente->id_user, $nombre, $sheetName, $logoPath)) {
                    $count++;
                }
            }

            if ($count === 0) {
                return ['error' => true, 'message' => 'Ningún docente tiene bloques de horario.', 'data' => []];
            }

            return [
                'error' => false,
                'message' => "Se exportó el horario de {$count} docente(s).",
                'data' => [
                    'contenido' => $this->serializar($spreadsheet),
                    'nombre_archivo' => 'Horarios_docentes.xlsx',
                ],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => 'Error en el servidor al exportar los horarios.', 'data' => []];
        }
    }

    /** Serializa el Spreadsheet a la cadena binaria .xlsx que espera el controller para el download. */
    private function serializar(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        $tmp = fopen('php://temp', 'r+');
        $writer->save($tmp);
        rewind($tmp);
        $contenido = stream_get_contents($tmp);
        fclose($tmp);

        return $contenido;
    }

    // ------------------------------------------------------------------
    // Datos: franjas completas del docente (incluidas las vacías) + lo
    // que realmente tiene agendado en cada una.
    // ------------------------------------------------------------------

    /** Homogeniza un HorarioClase (clase real, o RECESO/ALMUERZO/etc. creado a mano sin carga académica) a un array plano. */
    private function normalizar($item): array
    {
        $carga = $item->cargaAcademica;
        $da = $carga?->docenteAsignatura;

        return [
            'id' => $item->id,
            'id_franja_horaria' => $item->id_franja_horaria,
            'id_carga_academica' => $item->id_carga_academica,
            'tipo' => $item->tipo,
            'curso_nombre' => $carga?->curso?->nombre ?? '',
            'asignatura_nombre' => $da?->asignatura?->nombre ?? '',
            'nivel_nombre' => $carga?->curso?->nivel?->nombre ?? null,
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function textoCelda(array $f): string
    {
        $nombreAsignatura = $f['asignatura_nombre'] !== '' ? $f['asignatura_nombre'] : (self::TIPO_LABEL[$f['tipo']] ?? $f['tipo'] ?? '');

        return implode("\n", array_filter([$f['curso_nombre'], $nombreAsignatura], fn ($v) => $v !== '' && $v !== null));
    }

    /**
     * Arma, para un docente, la lista completa de franjas de TODOS los esquemas donde
     * dicta (año escolar activo) agrupadas por día — incluidas las franjas sin ninguna
     * clase agendada, para que la jornada se vea completa en vez de solo los huecos donde
     * hay algo. Un docente puede dictar en más de un esquema/nivel a la vez y todos
     * comparten la misma numeración horaria (ver HorarioClaseService::verHorario) — si dos
     * esquemas tienen una franja con la misma hora_inicio el mismo día, se tratan como una
     * sola fila (evita una fila "Martes 7:30" duplicada solo porque el docente también
     * dicta en otro nivel a esa misma hora de reloj).
     *
     * @return list<array{id:int,nombre:string,bloques:list<array{hora_inicio:string,hora_fin:string,asignable:bool,etiqueta:?string,color:?string,rows:list<array>}>}>
     */
    private function diasConFranjas(int $idDocente): array
    {
        $idsEsquemaAnioActivo = $this->horarioClaseService->idsEsquemaAnioActivo();
        $idsEsquema = $this->horarioClaseService->esquemasDelDocente($idDocente, $idsEsquemaAnioActivo);

        if ($idsEsquema->isEmpty()) {
            return [];
        }

        $franjas = FranjaHoraria::whereIn('id_esquema', $idsEsquema)
            ->with('diaSemana:id,nombre,abreviatura')
            ->orderBy('id_dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        if ($franjas->isEmpty()) {
            return [];
        }

        $porDia = [];
        foreach ($franjas as $franja) {
            $diaId = $franja->id_dia_semana;
            $key = $franja->hora_inicio;

            if (!isset($porDia[$diaId])) {
                $porDia[$diaId] = ['nombre' => $franja->diaSemana->nombre ?? '', 'abreviatura' => $franja->diaSemana->abreviatura ?? '', 'bloques' => []];
            }

            if (!isset($porDia[$diaId]['bloques'][$key])) {
                $porDia[$diaId]['bloques'][$key] = [
                    'hora_inicio' => $franja->hora_inicio,
                    'hora_fin' => $franja->hora_fin,
                    'asignable' => $franja->asignable,
                    'etiqueta' => $franja->etiqueta,
                    'color' => $franja->color,
                    'ids_franja' => [],
                ];
            }

            $porDia[$diaId]['bloques'][$key]['ids_franja'][] = $franja->id;
        }
        ksort($porDia);

        // Lo que este docente realmente tiene agendado, indexado por id_franja_horaria —
        // incluye clases reales y bloques RECESO/ALMUERZO/etc. creados a mano sin carga
        // académica (esos sí son HorarioClase reales, no se pierden por no tener franja
        // "vacía": simplemente coinciden con una franja que ya iba a listarse igual).
        $porFranjaId = [];
        foreach ($this->horarioClaseService->verHorario($idDocente, null, null, null, false, true)['data'] ?? [] as $item) {
            $f = $this->normalizar($item);
            $porFranjaId[$f['id_franja_horaria']][] = $f;
        }

        $esClaseReal = fn (array $r) => $r['id_carga_academica'] !== null;

        $dias = [];
        foreach ($porDia as $diaId => $dia) {
            $bloques = array_values($dia['bloques']);
            foreach ($bloques as &$bloque) {
                $rows = [];
                foreach ($bloque['ids_franja'] as $idFranja) {
                    foreach ($porFranjaId[$idFranja] ?? [] as $f) {
                        $rows[] = $f;
                    }
                }

                // Si la franja (deduplicada entre esquemas) tiene una clase real Y algo más
                // (receso/almuerzo sin carga de OTRO esquema a la misma hora de reloj), el
                // docente no puede estar libre y dando clase a la vez — se descarta lo demás.
                $clasesReales = array_values(array_filter($rows, $esClaseReal));
                $bloque['rows'] = count($clasesReales) > 0 ? $clasesReales : $rows;
            }
            unset($bloque);

            $dias[] = ['id' => $diaId, 'nombre' => $dia['nombre'], 'abreviatura' => $dia['abreviatura'], 'bloques' => $bloques];
        }

        return $dias;
    }

    /**
     * Bloques consecutivos de la MISMA clase real (misma id_carga_academica) en un día se
     * fusionan en un solo "run" (una sola celda con rowspan al exportar). Nunca fusiona una
     * celda con varios ocupantes, ni un receso/almuerzo/franja vacía.
     *
     * @param list<array{rows:list<array>}> $bloques
     * @return array<int,array{startIdx:int,endIdx:int}>
     */
    private function agruparRunsDia(array $bloques): array
    {
        $n = count($bloques);
        $runByIdx = [];
        $idx = 0;

        while ($idx < $n) {
            $rows = $bloques[$idx]['rows'];
            $esClaseUnica = count($rows) === 1 && $rows[0]['id_carga_academica'] !== null;

            $endIdx = $idx;
            if ($esClaseUnica) {
                $base = $rows[0];
                while ($endIdx + 1 < $n) {
                    $siguientes = $bloques[$endIdx + 1]['rows'];
                    $candidata = count($siguientes) === 1 ? $siguientes[0] : null;

                    if ($candidata === null || $candidata['id_carga_academica'] !== $base['id_carga_academica']) {
                        break;
                    }

                    $endIdx++;
                }
            }

            for ($i = $idx; $i <= $endIdx; $i++) {
                $runByIdx[$i] = ['startIdx' => $idx, 'endIdx' => $endIdx];
            }

            $idx = $endIdx + 1;
        }

        return $runByIdx;
    }

    private function formatHoraRango(string $inicio, string $fin): string
    {
        $fmt = function (string $hms): string {
            $partes = explode(':', $hms);

            if (count($partes) < 2) {
                return substr($hms, 0, 5);
            }

            $h = (int) $partes[0];
            $meridiano = $h < 12 ? 'a. m.' : 'p. m.';
            $h12 = $h % 12 === 0 ? 12 : $h % 12;

            return sprintf('%02d:%s %s', $h12, $partes[1], $meridiano);
        };

        return "{$fmt($inicio)} – {$fmt($fin)}";
    }

    /**
     * Excel no permite \ / * ? : [ ] en un nombre de hoja y lo trunca a 31 caracteres — si
     * dos docentes coinciden en el nombre saneado, se numeran para no pisarse entre sí.
     */
    private function nombreHojaUnico(string $nombre, array &$usados): string
    {
        $base = substr(preg_replace('/[\\\\\/\*\?:\[\]]/', '', trim($nombre)), 0, 31);
        $base = $base !== '' ? $base : 'Docente';
        $candidato = $base;
        $i = 2;

        while (in_array(mb_strtolower($candidato), $usados, true)) {
            $sufijo = " ({$i})";
            $candidato = substr($base, 0, 31 - strlen($sufijo)).$sufijo;
            $i++;
        }

        $usados[] = mb_strtolower($candidato);

        return $candidato;
    }

    /** "#00d6e6" o "00d6e6" -> "FF00D6E6" (ARGB opaco que espera PhpSpreadsheet). */
    private function toArgb(?string $hex): string
    {
        $hex = strtoupper(str_replace('#', '', $hex ?? '7f8c8d'));

        return 'FF'.str_pad($hex, 6, '0', STR_PAD_LEFT);
    }

    private function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Arma una hoja con la grilla de un docente dentro de un Spreadsheet ya existente —
     * así se puede llamar una vez por docente y descargar un único .xlsx con varias hojas
     * (ver exportarTodosLosDocentes) en vez de un archivo por persona. Devuelve false (sin
     * agregar nada) si el docente no tiene ningún esquema de horario asignado.
     *
     * Layout: cada día ocupa DOS columnas (Hora-del-día + contenido) — no una columna
     * "Hora" única compartida — porque cada día trae su propia lista de franjas (pueden
     * venir de esquemas distintos, con jornadas distintas). Si un día tiene menos bloques
     * que otro, las filas sobrantes quedan en blanco para ese día.
     *
     * @param ?string $logoPath Logo resuelto por dominio de correo del docente (ver
     * MarcaDominioService::resolverPorCorreo) — null cae al logo genérico de OMNIA.
     */
    private function agregarHoja(Spreadsheet $spreadsheet, int $idDocente, string $nombreDocente, string $sheetName, ?string $logoPath = null): bool
    {
        $dias = $this->diasConFranjas($idDocente);

        if (count($dias) === 0) {
            return false;
        }

        foreach ($dias as &$dia) {
            $dia['runs'] = $this->agruparRunsDia($dia['bloques']);
        }
        unset($dia);

        $maxBloques = max(array_map(fn ($d) => count($d['bloques']), $dias));

        $todasLasFilas = array_merge(...array_map(fn ($d) => array_merge(...array_map(fn ($b) => $b['rows'], $d['bloques'])), $dias));
        $esClaseReal = fn (array $r) => $r['id_carga_academica'] !== null;
        $reales = array_values(array_filter($todasLasFilas, $esClaseReal));

        $asignaturasUnicas = collect($reales)->pluck('asignatura_nombre')->filter(fn ($v) => $v !== '')->unique()->sort()->values();
        $nivelesUnicos = collect($reales)->pluck('nivel_nombre')->filter()->unique()->sort()->values();
        $ultimaActualizacion = collect($todasLasFilas)->pluck('updated_at')->filter()->sort()->last();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);
        $sheet->setShowGridLines(false);

        $lastColIdx = 1 + count($dias) * 2;
        $lastColLetter = $this->col($lastColIdx);

        $sheet->getColumnDimension('A')->setWidth(22);
        foreach ($dias as $i => $dia) {
            $sheet->getColumnDimension($this->col(2 + $i * 2))->setWidth(11);
            $sheet->getColumnDimension($this->col(3 + $i * 2))->setWidth(24);
        }

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // Encabezado: A logo, nivel académico (tipo de esquema) arriba a la derecha
        // ocupando las dos últimas columnas de la TABLA (el par Hora+contenido del último
        // día) — así siempre queda sobre el último día real de este docente, sea cual sea,
        // en vez de depender de una posición fija. Teacher/Subject ocupan TODAS las
        // columnas centrales que queden libres entre el logo y el nivel académico.
        $colNivelFin = $lastColIdx;
        $colNivelInicio = max(2, $lastColIdx - 1);
        $colC = 2;
        $colD = max($colC, $colNivelInicio - 1);
        if ($colD >= $colNivelInicio) {
            // Muy pocos días en total (el nivel ya ocupa toda la fila) — Teacher/Subject
            // caen sobre la columna del logo en vez de pisar el nivel académico.
            $colC = $colD = 1;
        }

        $center = function ($style): void {
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        };

        $sheet->mergeCells("{$this->col($colNivelInicio)}1:{$this->col($colNivelFin)}4");
        $nivelCell = $sheet->getCell("{$this->col($colNivelInicio)}1");
        $nivelCell->setValue($nivelesUnicos->implode(', ') ?: '—');
        $nivelCell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(18)->setBold(true)->getColor()->setARGB('FF1F3864');
        $center($nivelCell->getStyle());

        $sheet->mergeCells("{$this->col($colC)}2:{$this->col($colD)}2");
        $teacherCell = $sheet->getCell("{$this->col($colC)}2");
        $teacherRich = new RichText();
        $teacherRich->createTextRun('Teacher: ')->getFont()->setName(self::FONT_NAME)->setSize(10)->setBold(true);
        $teacherRich->createTextRun($nombreDocente)->getFont()->setName(self::FONT_NAME)->setSize(10);
        $teacherCell->setValue($teacherRich);
        $center($teacherCell->getStyle());

        $sheet->mergeCells("{$this->col($colC)}3:{$this->col($colD)}3");
        $subjectCell = $sheet->getCell("{$this->col($colC)}3");
        $subjectRich = new RichText();
        $subjectRich->createTextRun('Subject: ')->getFont()->setName(self::FONT_NAME)->setSize(10)->setBold(true);
        $subjectRich->createTextRun($asignaturasUnicas->implode(', ') ?: '—')->getFont()->setName(self::FONT_NAME)->setSize(10);
        $subjectCell->setValue($subjectRich);
        $center($subjectCell->getStyle());

        // Logo — columna A, filas 1 a 4, centrado tanto horizontal (dentro del ancho de la
        // columna) como verticalmente (dentro de las 4 filas del encabezado). No se ancla
        // celda-a-celda como en el frontend (ExcelJS): se calcula el tamaño a partir de las
        // proporciones reales del archivo y se centra a mano con offsets en píxeles, ya que
        // PhpSpreadsheet no tiene un "fit dentro de este rango y centra" nativo.
        $logoPath ??= storage_path('app/public/images/horario/logo.png');
        if (is_file($logoPath)) {
            $logoInfo = getimagesize($logoPath);
            $ratio = $logoInfo ? $logoInfo[0] / $logoInfo[1] : 1;

            // Ancho de columna (caracteres) -> píxeles: aproximación estándar de Excel
            // (Calibri 11), suficiente para centrar sin depender de leer el .xlsx ya escrito.
            $colWidthPx = 22 * 7 + 5;
            $rowsHeightPx = (26 + 20 + 20 + 20) * 4 / 3;

            $logoWidth = $colWidthPx - 10;
            $logoHeight = $logoWidth / $ratio;
            if ($logoHeight > $rowsHeightPx - 10) {
                $logoHeight = $rowsHeightPx - 10;
                $logoWidth = $logoHeight * $ratio;
            }

            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setResizeProportional(true);
            $drawing->setWidth((int) round($logoWidth));
            $drawing->setOffsetX((int) round(max(0, ($colWidthPx - $logoWidth) / 2)));
            $drawing->setOffsetY((int) round(max(0, ($rowsHeightPx - $logoHeight) / 2)));
            $drawing->setWorksheet($sheet);
        }

        $headerRow = 5;
        for ($c = 1; $c <= $lastColIdx; $c++) {
            $sheet->getStyle("{$this->col($c)}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        }
        foreach ($dias as $i => $dia) {
            $horaCol = 2 + $i * 2;
            $contentCol = $horaCol + 1;

            $horaHeaderCell = $sheet->getCell("{$this->col($horaCol)}{$headerRow}");
            $horaHeaderCell->setValue('Hora '.ucfirst(mb_strtolower($dia['abreviatura'] ?: substr($dia['nombre'], 0, 3))));
            $horaHeaderCell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(10)->setBold(true);
            $center($horaHeaderCell->getStyle());

            $diaHeaderCell = $sheet->getCell("{$this->col($contentCol)}{$headerRow}");
            $diaHeaderCell->setValue($dia['nombre']);
            $diaHeaderCell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(11)->setBold(true);
            $center($diaHeaderCell->getStyle());
        }
        for ($c = 1; $c <= $lastColIdx; $c++) {
            $sheet->getStyle("{$this->col($c)}{$headerRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // Nunca se retiene un objeto Cell/Style entre dos llamadas a $sheet->getCell() —
        // PhpSpreadsheet reutiliza un único objeto Cell internamente y lo reata a la última
        // coordenada pedida, así que una referencia vieja queda "no vinculada a la hoja" en
        // cuanto se pide otra celda (error real: "Cannot update when cell is not bound to a
        // worksheet"). Todo acá va por coordenada (setCellValue/getStyle de la hoja).
        for ($idx = 0; $idx < $maxBloques; $idx++) {
            $row = $headerRow + 1 + $idx;
            $sheet->getRowDimension($row)->setRowHeight(32);

            foreach ($dias as $i => $dia) {
                $horaCol = 2 + $i * 2;
                $contentCol = $horaCol + 1;
                $horaCoord = "{$this->col($horaCol)}{$row}";
                $contentCoord = "{$this->col($contentCol)}{$row}";

                $horaStyle = $sheet->getStyle($horaCoord);
                $horaStyle->getFont()->setName(self::FONT_NAME)->setSize(8);
                $horaStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
                $horaStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                $center($horaStyle);

                $contentStyle = $sheet->getStyle($contentCoord);
                $contentStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                $center($contentStyle);
                $contentStyle->getFont()->setName(self::FONT_NAME)->setSize(9);

                if ($idx >= count($dia['bloques'])) {
                    // Este día tiene menos bloques que el día más largo — celdas en blanco.
                    $sheet->getStyle($contentCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
                    continue;
                }

                $bloque = $dia['bloques'][$idx];
                $sheet->setCellValue($horaCoord, $this->formatHoraRango($bloque['hora_inicio'], $bloque['hora_fin']));

                $run = $dia['runs'][$idx];

                if (count($bloque['rows']) === 0) {
                    // Franja vacía: si está marcada como no asignable (receso/almuerzo del
                    // esquema) se resalta con su color/etiqueta aunque nadie la haya
                    // agendado; si es una franja normal simplemente libre, queda en blanco.
                    if (!$bloque['asignable']) {
                        $sheet->setCellValue($contentCoord, $bloque['etiqueta'] ?? 'No asignable');
                        $contentStyle = $sheet->getStyle($contentCoord);
                        $contentStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($this->toArgb($bloque['color']));
                        $contentStyle->getFont()->setName(self::FONT_NAME)->setSize(9)->setBold(true);
                    } else {
                        $sheet->getStyle($contentCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
                    }
                    continue;
                }

                // Fila de continuación de un bloque fusionado: ya se escribió/fusionó en la
                // fila donde arrancó el run — acá solo hace falta el estilo de la celda
                // vacía que la fusión va a absorber.
                if ($run['startIdx'] !== $idx) {
                    $sheet->getStyle($contentCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
                    continue;
                }

                $sheet->setCellValue($contentCoord, implode("\n\n", array_map(fn ($r) => $this->textoCelda($r), $bloque['rows'])));
                $sheet->getStyle($contentCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');

                if ($run['endIdx'] > $run['startIdx']) {
                    $filaInicio = $headerRow + 1 + $run['startIdx'];
                    $filaFin = $headerRow + 1 + $run['endIdx'];
                    $sheet->mergeCells("{$this->col($contentCol)}{$filaInicio}:{$this->col($contentCol)}{$filaFin}");
                }
            }
        }

        $footerRow = $headerRow + $maxBloques + 1;
        $sheet->mergeCells("A{$footerRow}:{$lastColLetter}{$footerRow}");
        $footerCell = $sheet->getCell("A{$footerRow}");
        $footerCell->setValue('Actualizado: '.($ultimaActualizacion ? \Carbon\Carbon::parse($ultimaActualizacion)->locale('es')->isoFormat('D/M/YYYY, h:mm a') : '—'));
        $footerCell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(8)->setItalic(true)->getColor()->setARGB('FF595959');
        $footerCell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1)
            ->setPrintArea("A1:{$lastColLetter}{$footerRow}");

        return true;
    }
}

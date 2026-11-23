<?php

namespace App\Services\permisosLicencias;

use App\Models\PermisosLicencias\Permiso;
use App\Services\branding\MarcaDominioService;
use Exception;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Genera el .xlsx del listado de permisos/licencias (mismo recorte de filtros que el listado en pantalla). */
class PermisosLicenciasExcelService
{
    private const FONT_NAME = 'Arial';

    private const ESTADO_COLOR = [0 => 'FFFFFF00', 1 => 'FF92D050', 2 => 'FFFF0000'];

    private const COLUMNAS_DIA_COMPLETO = ['Nombre', 'Documento', 'Fecha inicio', 'Fecha final', 'No. días', 'Motivo', 'Descripción', 'Remunerado', 'Evidencia', 'Estado'];

    private const COLUMNAS_PARCIAL = ['Nombre', 'Documento', 'Fecha permiso', 'Hora salida', 'Hora regreso', 'Tiempo solicitado (min)', 'Motivo', 'Descripción', 'Remunerado', 'Evidencia', 'Estado'];

    public function __construct(
        private PermisosLicenciasServices $service,
        private MarcaDominioService $marcaDominioService,
    ) {}

    public function exportar(array $filtros, ?int $idNivelViewer, ?string $correoUsuario): array
    {
        try {
            $respuesta = $this->service->exportar($filtros, $idNivelViewer);

            if ($respuesta['error']) {
                return $respuesta;
            }

            $permisos = $respuesta['data'];
            $diaCompleto = $permisos->where('tipo_permiso', 2);
            $parciales = $permisos->where('tipo_permiso', 1);

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Permisos y licencias');

            $this->agregarEncabezado($sheet, $correoUsuario);

            $fila = $this->agregarTabla($sheet, 6, 'PERMISOS DE DÍA COMPLETO', self::COLUMNAS_DIA_COMPLETO, $diaCompleto, fn ($p) => [
                $this->nombreUsuario($p), $p->usuario?->documento ?? '—',
                $p->fecha_permiso?->format('d/m/Y') ?? '—', $p->fecha_retorno?->format('d/m/Y') ?? '—',
                $p->dias_permiso ?? '—', $p->motivo?->nombre ?? '—', $p->descripcion ?? '—',
                ucfirst($p->remunerado ?? '—'), $p->evidencia_permiso ? 'Sí' : 'No', PermisosLicenciasServices::ESTADOS[$p->estado] ?? '—',
            ]);

            $this->agregarTabla($sheet, $fila + 2, 'PERMISOS PARCIALES', self::COLUMNAS_PARCIAL, $parciales, fn ($p) => [
                $this->nombreUsuario($p), $p->usuario?->documento ?? '—',
                $p->fecha_permiso?->format('d/m/Y') ?? '—', $p->hora_salida ?? '—',
                $this->horaRegreso($p->hora_salida, $p->tiempo_permiso), $p->tiempo_permiso ?? '—',
                $p->motivo?->nombre ?? '—', trim(($p->tipo_permiso_detalle ? "{$p->tipo_permiso_detalle} - " : '').($p->descripcion ?? '')) ?: '—',
                ucfirst($p->remunerado ?? '—'), $p->evidencia_permiso ? 'Sí' : 'No', PermisosLicenciasServices::ESTADOS[$p->estado] ?? '—',
            ]);

            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            return [
                'error' => false,
                'message' => 'Permisos/licencias exportados correctamente.',
                'data' => [
                    'contenido' => $this->serializar($spreadsheet),
                    'nombre_archivo' => 'Permisos_licencias_'.now()->format('Y-m-d').'.xlsx',
                ],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => 'Error en el servidor al exportar los permisos/licencias.', 'data' => []];
        }
    }

    private function nombreUsuario(Permiso $permiso): string
    {
        return trim("{$permiso->usuario?->nombre} {$permiso->usuario?->apellido}") ?: "#{$permiso->id_user}";
    }

    /** Suma minutos a una hora `H:i:s`/`H:i` — null si falta alguno de los dos datos. */
    private function horaRegreso(?string $horaSalida, $tiempoPermisoMin): string
    {
        if (! $horaSalida || ! is_numeric($tiempoPermisoMin)) {
            return '—';
        }

        return \Carbon\Carbon::createFromFormat('H:i:s', strlen($horaSalida) === 5 ? "{$horaSalida}:00" : $horaSalida)
            ->addMinutes((int) $tiempoPermisoMin)
            ->format('H:i');
    }

    private function agregarEncabezado($sheet, ?string $correoUsuario): void
    {
        $sheet->mergeCells('B1:H2');
        $titulo = $sheet->getCell('B1');
        $titulo->setValue('Permisos y licencias — generado el '.now()->format('d/m/Y H:i'));
        $titulo->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(14)->setBold(true);
        $titulo->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(26);

        $logoPath = $this->marcaDominioService->rutaLocalLogo($correoUsuario);
        if ($logoPath && is_file($logoPath)) {
            $logoInfo = getimagesize($logoPath);
            $ratio = $logoInfo ? $logoInfo[0] / $logoInfo[1] : 1;
            $alto = 48;

            $drawing = new Drawing;
            $drawing->setName('Escudo');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setResizeProportional(true);
            $drawing->setHeight($alto);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }
    }

    /**
     * @param  callable(Permiso):array  $filaDeRegistro
     * @return int última fila escrita
     */
    private function agregarTabla($sheet, int $filaInicio, string $titulo, array $columnas, Collection $registros, callable $filaDeRegistro): int
    {
        $ultimaCol = chr(ord('A') + count($columnas) - 1);

        $sheet->mergeCells("A{$filaInicio}:{$ultimaCol}{$filaInicio}");
        $tituloCell = $sheet->getCell("A{$filaInicio}");
        $tituloCell->setValue($titulo);
        $tituloCell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(11)->setBold(true);

        $filaEncabezado = $filaInicio + 1;
        foreach ($columnas as $i => $nombre) {
            $col = chr(ord('A') + $i);
            $cell = $sheet->getCell("{$col}{$filaEncabezado}");
            $cell->setValue($nombre);
            $cell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(10)->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $cell->getStyle()->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $fila = $filaEncabezado;
        foreach ($registros as $registro) {
            $fila++;
            $valores = $filaDeRegistro($registro);

            foreach ($valores as $i => $valor) {
                $col = chr(ord('A') + $i);
                $cell = $sheet->getCell("{$col}{$fila}");
                $cell->setValue($valor);
                $cell->getStyle()->getFont()->setName(self::FONT_NAME)->setSize(9);
                $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $cell->getStyle()->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }

            $colEstado = $ultimaCol;
            $sheet->getStyle("{$colEstado}{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ESTADO_COLOR[$registro->estado] ?? 'FFFFFFFF');
        }

        return max($fila, $filaEncabezado);
    }

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
}

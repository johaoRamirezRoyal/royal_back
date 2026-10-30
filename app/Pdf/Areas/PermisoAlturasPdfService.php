<?php

namespace App\Pdf\Areas;

use App\Services\Cloudinary\CloudinaryService;
use TCPDF;

/**
 * Permiso de Trabajo en Alturas (Resolución 4272 de 2021, MinTrabajo — trabajos desde 2 m)
 * para un mantenimiento de Áreas Comunes con operativo asignado. Los datos del trabajo y del
 * trabajador salen del sistema; la lista de verificación, la altura y las firmas se llenan en
 * sitio antes de empezar. Misma técnica (TCPDF + HTML) que ReporteOperativoPdfService.
 */
class PermisoAlturasPdfService
{
    /** Verificaciones previas mínimas del permiso (Res. 4272/2021). */
    private const VERIFICACIONES = [
        'Afiliación vigente a seguridad social (EPS, AFP y ARL)',
        'Certificado de aptitud médica para trabajo en alturas',
        'Certificado de curso de trabajo en alturas vigente',
        'Arnés de cuerpo completo inspeccionado',
        'Eslinga con absorbedor de energía / línea de vida',
        'Puntos de anclaje certificados o verificados',
        'Casco con barbuquejo, gafas y guantes',
        'Escalera o andamio inspeccionado y en buen estado',
        'Área delimitada y señalizada',
        'Condiciones climáticas aptas (sin lluvia ni tormenta eléctrica)',
        'Herramientas aseguradas contra caída',
        'Plan de emergencia y rescate disponible',
    ];

    /**
     * @param  array{consecutivo: int|string, tipo: string, fecha_emision: string, fecha_trabajo: string, bloque: string, area: string, item: string, descripcion: string, operativo: string, documento: string, curso_expedicion: string, curso_vigencia: string}  $data
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
                $this->SetFont($this->getFontFamily(), 'I', 8);
                $this->Cell(0, 10, 'Válido solo para la jornada indicada. Si cambian las condiciones del trabajo, se suspende y se emite un nuevo permiso.', 0, 0, 'C');
            }
        };

        $pdf->SetCreator('OMNIA');
        $pdf->SetAuthor('OMNIA');
        $pdf->SetTitle('Permiso de trabajo en alturas');
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Logo institucional fijo (no el de la marca por dominio) — ver AGENTS.md "Logo en PDFs".
        $logoPath = app(CloudinaryService::class)->rutaLocalRoyalSchool('logotipoBackground.png');
        $logoCell = $logoPath && is_file($logoPath) ? '<img src="'.$logoPath.'" width="120">' : '';

        $filasVerificacion = '';
        foreach (self::VERIFICACIONES as $i => $texto) {
            $filasVerificacion .= '<tr><td style="width:6%;">'.($i + 1).'</td><td style="width:70%; text-align:left;">'.e($texto).'</td><td style="width:12%;"></td><td style="width:12%;"></td></tr>';
        }

        $html = '
        <table border="1" cellpadding="4" style="width:100%; text-align:center;">
            <tr>
                <td style="width:30%;">'.$logoCell.'</td>
                <td style="width:48%; font-size:11px; font-weight:bold;"><br><br>PERMISO DE TRABAJO EN ALTURAS<br><span style="font-size:8px; font-weight:normal;">Resolución 4272 de 2021 — Ministerio del Trabajo</span></td>
                <td style="width:22%; font-size:9px;"><br><b>N.° '.e((string) $data['consecutivo']).'</b><br>Emitido: '.e($data['fecha_emision']).'</td>
            </tr>
        </table>
        <br><br>
        <table border="1" cellpadding="3" style="width:100%; font-size:9px;">
            <tr style="background-color:#e6e6e6; font-weight:bold;"><td colspan="4">1. DATOS DEL TRABAJO</td></tr>
            <tr>
                <td style="width:20%;"><b>Tipo</b></td><td style="width:30%;">'.e($data['tipo']).'</td>
                <td style="width:20%;"><b>Fecha del trabajo</b></td><td style="width:30%;">'.e($data['fecha_trabajo']).'</td>
            </tr>
            <tr>
                <td><b>Bloque</b></td><td>'.e($data['bloque']).'</td>
                <td><b>Área</b></td><td>'.e($data['area']).'</td>
            </tr>
            <tr>
                <td><b>Ítem / elemento</b></td><td>'.e($data['item']).'</td>
                <td><b>Altura aproximada</b></td><td>________ m</td>
            </tr>
            <tr>
                <td><b>Hora de inicio</b></td><td>__________</td>
                <td><b>Hora de finalización</b></td><td>__________</td>
            </tr>
            <tr><td><b>Descripción de la tarea</b></td><td colspan="3">'.e($data['descripcion']).'</td></tr>
        </table>
        <br><br>
        <table border="1" cellpadding="3" style="width:100%; font-size:9px;">
            <tr style="background-color:#e6e6e6; font-weight:bold;"><td colspan="4">2. TRABAJADOR AUTORIZADO</td></tr>
            <tr>
                <td style="width:20%;"><b>Nombre</b></td><td style="width:30%;">'.e($data['operativo']).'</td>
                <td style="width:20%;"><b>Documento</b></td><td style="width:30%;">'.e($data['documento']).'</td>
            </tr>
            <tr>
                <td><b>Curso de alturas expedido</b></td><td>'.e($data['curso_expedicion']).'</td>
                <td><b>Vigente hasta</b></td><td>'.e($data['curso_vigencia']).'</td>
            </tr>
        </table>
        <br><br>
        <table border="1" cellpadding="3" style="width:100%; font-size:9px; text-align:center;">
            <tr style="background-color:#e6e6e6; font-weight:bold;"><td colspan="4" style="text-align:left;">3. LISTA DE VERIFICACIÓN PREVIA</td></tr>
            <tr style="font-weight:bold;"><td style="width:6%;">#</td><td style="width:70%;">Verificación</td><td style="width:12%;">SÍ</td><td style="width:12%;">NO</td></tr>
            '.$filasVerificacion.'
        </table>
        <br><br>
        <table border="1" cellpadding="3" style="width:100%; font-size:9px;">
            <tr style="background-color:#e6e6e6; font-weight:bold;"><td>4. OBSERVACIONES</td></tr>
            <tr><td><br><br><br></td></tr>
        </table>
        <br><br><br><br>
        <table style="width:100%; font-size:8px; text-align:center;">
            <tr>
                <td style="width:33%;">__________________________<br>'.e($data['operativo']).'</td>
                <td style="width:34%;">__________________________<br><br></td>
                <td style="width:33%;">__________________________<br><br></td>
            </tr>
            <tr style="font-weight:bold;">
                <td>Trabajador autorizado</td><td>Coordinador de trabajo en alturas</td><td>Autoriza (SST / Gestión Humana)</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}

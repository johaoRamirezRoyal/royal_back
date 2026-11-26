<?php

namespace Tests\Feature\Tramites;

use App\Mail\GenericMail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenericMailAdjuntosTest extends TestCase
{
    public function test_adjunta_los_archivos_reales_con_su_nombre_original(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tramites/documentos/a.pdf', '%PDF-1 contenido');
        Storage::disk('public')->put('tramites/documentos/b.xlsx', 'xlsx');

        $mail = (new GenericMail('Asunto', 'Cuerpo', null, 'Ver', [
            ['ruta' => 'tramites/documentos/a.pdf', 'nombre' => 'Certificado.pdf'],
            ['ruta' => 'tramites/documentos/b.xlsx', 'nombre' => 'Detalle.xlsx'],
        ]))->build();

        $this->assertCount(2, $mail->attachments);
        $this->assertSame('Certificado.pdf', $mail->attachments[0]['options']['as']);
        $this->assertSame(Storage::disk('public')->path('tramites/documentos/a.pdf'), $mail->attachments[0]['file']);
        $this->assertSame('Detalle.xlsx', $mail->attachments[1]['options']['as']);
    }

    public function test_un_archivo_inexistente_se_omite_sin_romper_el_correo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tramites/documentos/a.pdf', 'pdf');

        $mail = (new GenericMail('Asunto', 'Cuerpo', null, 'Ver', [
            ['ruta' => 'tramites/documentos/no-existe.pdf', 'nombre' => 'Perdido.pdf'],
            ['ruta' => 'tramites/documentos/a.pdf', 'nombre' => 'Bueno.pdf'],
        ]))->build();

        $this->assertCount(1, $mail->attachments);
        $this->assertSame('Bueno.pdf', $mail->attachments[0]['options']['as']);
    }

    public function test_sin_adjuntos_no_adjunta_nada(): void
    {
        $mail = (new GenericMail('Asunto', 'Cuerpo'))->build();

        $this->assertCount(0, $mail->attachments);
    }
}

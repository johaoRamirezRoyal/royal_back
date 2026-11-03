<?php

namespace Tests\Unit\Cloudinary;

use App\Http\Controllers\Documentos\DocumentoController;
use App\Services\Cloudinary\ArchivosPrivados;
use App\Services\Cloudinary\CloudinaryUploadsAdapter;
use Cloudinary\Cloudinary;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Tests\TestCase;

/**
 * Los documentos privados pasan por Laravel (no vence una URL firmada: seguiría abriendo
 * después de cerrar sesión); las fotos se redirigen a la firmada sin guardar el 302.
 */
class DocumentosPrivadosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 's', 'filesystems.uploads_disk' => 'prueba']);

        $adapter = new CloudinaryUploadsAdapter(new Cloudinary('cloudinary://k:s@nube'), 'royal');
        Storage::set('prueba', new FilesystemAdapter(new Filesystem($adapter), $adapter));
    }

    /** Mismo resultado que streamedContent(), sin depender de la versión de Laravel. */
    private function contenido($respuesta): string
    {
        ob_start();
        $respuesta->sendContent();

        return (string) ob_get_clean();
    }

    public function test_un_token_alterado_no_abre(): void
    {
        $this->assertNull(ArchivosPrivados::deToken('nada'));
        $this->assertNull(ArchivosPrivados::deToken(''));
    }

    public function test_el_proxy_transmite_el_documento_sin_cache(): void
    {
        Http::fake([ArchivosPrivados::url('nube', 's', 'raw', 'royal/Admisiones/documentos/1/acta.docx') => Http::response('bytes', 200, ['Content-Type' => 'application/octet-stream'])]);
        $token = explode('/', ArchivosPrivados::paraCliente('https://res.cloudinary.com/nube/raw/upload/v9/royal/Admisiones/documentos/1/acta.docx'))[5];

        $respuesta = app(DocumentoController::class)->privado(new Request, $token);

        $this->assertSame('bytes', $this->contenido($respuesta));
        // El tipo de un `raw` sale de la extensión.
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline; filename=', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_api_public_transmite_un_certificado_y_redirige_una_foto_sin_cache(): void
    {
        $certificado = ArchivosPrivados::url('nube', 's', 'image', 'royal/certificados/c.pdf');
        Http::fake([$certificado => Http::response('pdf', 200, ['Content-Type' => 'application/pdf'])]);
        $controlador = app(DocumentoController::class);

        $documento = $controlador->verPublico(new Request(['descargar' => '1']), 'certificados/c.pdf');
        $this->assertSame('pdf', $this->contenido($documento));
        $this->assertStringStartsWith('attachment; filename=', $documento->headers->get('Content-Disposition'));

        // Foto de carnet y foto de la raíz (perfil): redirección a la firmada, sin guardar el 302.
        foreach (['carnets/a.jpg' => 'royal/carnets/a.jpg', 'perfil.jpg' => 'royal/perfil.jpg'] as $ruta => $archivo) {
            $foto = $controlador->verPublico(new Request(['w' => 128]), $ruta);
            $this->assertSame(302, $foto->getStatusCode(), $ruta);
            $this->assertSame(ArchivosPrivados::url('nube', 's', 'image', $archivo, 'w_128,c_limit,f_auto,q_auto'), $foto->headers->get('Location'));
            $this->assertStringContainsString('no-store', $foto->headers->get('Cache-Control'));
        }
    }
}

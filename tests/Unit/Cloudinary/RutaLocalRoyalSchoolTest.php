<?php

namespace Tests\Unit\Cloudinary;

use App\Services\Cloudinary\ArchivosPrivados;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Los logos de PDFs/Excel salen de royal/royal-school en Cloudinary: si cambia la URL,
 * los documentos salen sin logo.
 */
class RutaLocalRoyalSchoolTest extends TestCase
{
    public function test_descarga_el_archivo_a_un_temporal_local(): void
    {
        config(['cloudinary.cloud_name' => 'nube']);
        Http::fake(['res.cloudinary.com/nube/image/upload/royal/royal-school/paz-salvo/logo.webp' => Http::response('bytes')]);

        $ruta = app(CloudinaryService::class)->rutaLocalRoyalSchool('paz-salvo/logo.webp');

        $this->assertNotNull($ruta);
        $this->assertStringEndsWith('.webp', $ruta);
        $this->assertSame('bytes', file_get_contents($ruta));
    }

    public function test_la_firma_y_el_sello_se_piden_firmados(): void
    {
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 'secreto']);
        Http::fake([
            ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/royal-school/firma_administrativa.jpg') => Http::response('firma_administrativa.jpg'),
            ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/royal-school/paz-salvo/sello.webp') => Http::response('paz-salvo/sello.webp'),
            '*' => Http::response('', 401), // sin firmar
        ]);

        foreach (['firma_administrativa.jpg', 'paz-salvo/sello.webp'] as $archivo) {
            $ruta = app(CloudinaryService::class)->rutaLocalRoyalSchool($archivo);

            $this->assertNotNull($ruta, $archivo);
            $this->assertSame($archivo, file_get_contents($ruta));
        }
    }

    public function test_devuelve_null_si_cloudinary_falla(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertNull(app(CloudinaryService::class)->rutaLocalRoyalSchool('logotipoBackground.png'));
    }
}

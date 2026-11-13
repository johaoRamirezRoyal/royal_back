<?php

namespace Tests\Unit\Cloudinary;

use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fuera de producción el .env trae las credenciales reales de Cloudinary: si CloudinaryService
 * no respetara el disco de uploads, una prueba en local subiría o borraría en la nube de producción.
 */
class CloudinaryLocalTest extends TestCase
{
    public function test_fuera_de_produccion_guarda_y_borra_en_disco_local_sin_tocar_cloudinary(): void
    {
        config([
            // Aunque el disco diga Cloudinary: en local/testing manda el entorno.
            'filesystems.uploads_disk' => 'cloudinary',
            // Credenciales inválidas: si se llamara a Cloudinary, la subida fallaría.
            'cloudinary.cloud_name' => 'no-existe',
            'cloudinary.api_key' => 'x',
            'cloudinary.api_secret' => 'x',
        ]);
        Storage::fake('public');
        $servicio = new CloudinaryService;

        $this->assertFalse(CloudinaryService::usaCloudinary());

        $subida = $servicio->uploadFile(UploadedFile::fake()->create('firma.png', 5, 'image/png'), 'royal/usuarios/firmas', 'firma_7_ab12cd34');

        $this->assertFalse($subida['error'], $subida['message']);
        Storage::disk('public')->assertExists('royal/usuarios/firmas/firma_7_ab12cd34.png');
        $this->assertStringContainsString('/api/public/royal/usuarios/firmas/firma_7_ab12cd34.png', $subida['data']['url']);
        $this->assertSame('royal/usuarios/firmas/firma_7_ab12cd34', $subida['data']['public_id']);

        // Una URL de Cloudinary (datos copiados de producción) no se borra desde local.
        $this->assertFalse($servicio->deleteFileByUrl('https://res.cloudinary.com/no-existe/image/authenticated/royal/usuarios/firmas/otra.png')['error']);

        // Lo subido en local sí: por URL y por public_id (sin extensión, como las imágenes).
        $this->assertFalse($servicio->deleteFileByUrl($subida['data']['url'])['error']);
        Storage::disk('public')->assertMissing('royal/usuarios/firmas/firma_7_ab12cd34.png');

        $otra = $servicio->uploadFile(UploadedFile::fake()->create('f.png', 5, 'image/png'), 'royal/historia-clinica/firmas', 'f_1');
        $this->assertFalse($servicio->deleteFile($otra['data']['public_id'], 'image')['error']);
        Storage::disk('public')->assertMissing('royal/historia-clinica/firmas/f_1.png');
    }

    public function test_en_produccion_siempre_usa_cloudinary(): void
    {
        foreach (['production', 'staging'] as $entorno) {
            $this->app['env'] = $entorno;
            config(['filesystems.uploads_disk' => 'public']); // un UPLOADS_DISK mal puesto no cambia nada
            $this->assertTrue(CloudinaryService::usaCloudinary(), $entorno);
        }
        $this->app['env'] = 'testing';
    }
}

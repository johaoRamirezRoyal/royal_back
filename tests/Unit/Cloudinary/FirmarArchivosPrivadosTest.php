<?php

namespace Tests\Unit\Cloudinary;

use App\Http\Middleware\FirmarArchivosPrivados;
use App\Services\Cloudinary\ArchivosPrivados;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class FirmarArchivosPrivadosTest extends TestCase
{
    private const GUARDADA = 'https://res.cloudinary.com/nube/image/upload/v1/royal/Admisiones/documentos/1/c.pdf';

    private function responder(): string
    {
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 'secreto']);

        return (new FirmarArchivosPrivados)->handle(new Request, fn () => response()->json([
            'data' => [['url_archivo' => self::GUARDADA, 'extra' => new \stdClass]],
        ]))->getContent();
    }

    public function test_con_sesion_firma_y_conserva_los_objetos_vacios(): void
    {
        $this->actingAs(new User, 'api');

        $this->assertSame(
            json_encode(['data' => [['url_archivo' => ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/Admisiones/documentos/1/c.pdf'), 'extra' => new \stdClass]]]),
            $this->responder()
        );
    }

    public function test_sin_sesion_no_firma(): void
    {
        $this->assertStringNotContainsString('authenticated', $this->responder());
    }
}

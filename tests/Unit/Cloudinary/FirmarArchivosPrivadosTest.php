<?php

namespace Tests\Unit\Cloudinary;

use App\Http\Middleware\FirmarArchivosPrivados;
use App\Services\Cloudinary\ArchivosPrivados;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class FirmarArchivosPrivadosTest extends TestCase
{
    private const DOCUMENTO = 'https://res.cloudinary.com/nube/image/upload/v1/royal/Admisiones/documentos/1/c.pdf';

    private const FOTO = 'https://res.cloudinary.com/nube/image/upload/royal/carnets/f.jpg';

    private function responder(): array
    {
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 'secreto']);

        $respuesta = (new FirmarArchivosPrivados)->handle(new Request, fn () => response()->json([
            'data' => [['url_archivo' => self::DOCUMENTO, 'foto' => self::FOTO, 'extra' => new \stdClass]],
        ]));

        return [$respuesta->getContent(), $respuesta->getData()->data[0]];
    }

    public function test_con_sesion_el_documento_va_por_el_proxy_y_la_foto_firmada(): void
    {
        $this->actingAs(new User, 'api');
        [$json, $fila] = $this->responder();

        $this->assertMatchesRegularExpression('#/api/privado/[A-Za-z0-9_-]+/c\.pdf$#', $fila->url_archivo);
        $this->assertSame(['image', 'royal/Admisiones/documentos/1/c.pdf'], ArchivosPrivados::deToken(explode('/', $fila->url_archivo)[5]));
        $this->assertSame(ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/carnets/f.jpg'), $fila->foto);
        // getData() sin `true`: un {} vacío no pasa a [].
        $this->assertStringContainsString('"extra":{}', $json);
    }

    public function test_sin_sesion_no_toca_nada(): void
    {
        [$json] = $this->responder();

        $this->assertStringNotContainsString('authenticated', $json);
        $this->assertStringNotContainsString('privado', $json);
    }
}

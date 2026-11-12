<?php

namespace Tests\Unit\Branding;

use App\Services\branding\MarcaDominioService;
use App\Services\Cloudinary\CloudinaryService;
use Tests\TestCase;

/**
 * Logo de PDFs/Excel: el de la marca del dominio o, sin marca, el fijo de Royal School; un
 * WEBP sale convertido a PNG (TCPDF no lo soporta). Sin BD ni Cloudinary reales.
 */
class RutaLocalLogoTest extends TestCase
{
    private function servicio(?string $logoMarca): MarcaDominioService
    {
        $nube = new class extends CloudinaryService
        {
            public function __construct() {}

            public function rutaLocalRoyalSchool(string $archivo): ?string
            {
                return "royal/{$archivo}";
            }
        };

        return new class($nube, $logoMarca) extends MarcaDominioService
        {
            public function __construct(CloudinaryService $nube, private ?string $logoMarca)
            {
                parent::__construct($nube);
            }

            public function resolverRutaLocalPorCorreo(?string $correo): ?string
            {
                return $this->logoMarca;
            }
        };
    }

    public function test_usa_el_logo_de_la_marca_del_dominio(): void
    {
        $this->assertSame('/tmp/marca.png', $this->servicio('/tmp/marca.png')->rutaLocalLogo('a@colegio.edu.co'));
    }

    public function test_sin_marca_cae_al_logo_de_royal_school(): void
    {
        $this->assertSame('royal/logotipoBackground.png', $this->servicio(null)->rutaLocalLogo('a@gmail.com'));
    }

    public function test_un_logo_webp_sale_convertido_a_png(): void
    {
        $webp = tempnam(sys_get_temp_dir(), 'logo').'.webp';
        imagewebp(imagecreatetruecolor(4, 4), $webp);

        $ruta = $this->servicio($webp)->rutaLocalLogo('a@colegio.edu.co');

        $this->assertStringEndsWith('.png', $ruta);
        $this->assertSame(IMAGETYPE_PNG, getimagesize($ruta)[2]);
    }
}

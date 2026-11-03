<?php

namespace Tests\Unit\Cloudinary;

use App\Services\Cloudinary\ArchivosPrivados;
use App\Services\Cloudinary\CloudinaryUploadsAdapter;
use Cloudinary\Cloudinary;
use Tests\TestCase;

/**
 * El direccionamiento tiene que coincidir con lo que dejó la migración (y con
 * src/utils/fileUrl.ts del frontend): si cambia, los archivos migrados dan 404.
 */
class CloudinaryUploadsAdapterTest extends TestCase
{
    private function adapter(): CloudinaryUploadsAdapter
    {
        return new CloudinaryUploadsAdapter(new Cloudinary('cloudinary://k:s@nube'), 'royal');
    }

    public function test_imagen_en_carpeta_va_como_image_sin_extension_en_el_public_id(): void
    {
        $this->assertSame(
            ['royal/biblioteca/abc', 'image', 'https://res.cloudinary.com/nube/image/upload/royal/biblioteca/abc.jpg', 'upload'],
            $this->adapter()->recurso('biblioteca/abc.jpg')
        );
    }

    public function test_pdf_en_la_raiz_va_como_image_y_privado(): void
    {
        [$id, $tipo, $url, $entrega] = $this->adapter()->recurso('x_formacion.pdf');

        $this->assertSame(['royal/x_formacion', 'image', 'authenticated'], [$id, $tipo, $entrega]);
        $this->assertMatchesRegularExpression('#^https://res\.cloudinary\.com/nube/image/authenticated/s--[\w-]{8}--/v1/royal/x_formacion\.pdf$#', $url);
    }

    public function test_office_va_como_raw_con_extension_en_el_public_id(): void
    {
        $this->assertSame(
            ['royal/inventario/programacion-mant/y.docx', 'raw', 'https://res.cloudinary.com/nube/raw/upload/royal/inventario/programacion-mant/y.docx', 'upload'],
            $this->adapter()->recurso('inventario/programacion-mant/y.DOCX')
        );
    }

    public function test_foto_de_carnet_es_privada_y_firma_la_miniatura(): void
    {
        [, , $url, $entrega] = $this->adapter()->recurso('carnets/a.jpg', 'w_128,c_limit');

        $this->assertSame('authenticated', $entrega);
        $this->assertSame(ArchivosPrivados::url('nube', 's', 'image', 'royal/carnets/a.jpg', 'w_128,c_limit'), $url);
    }

    public function test_plantilla_de_carnet_sigue_publica_con_transformacion(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/nube/image/upload/w_128/royal/carnets/plantillas/p.png',
            $this->adapter()->recurso('carnets/plantillas/p.png', 'w_128')[2]
        );
    }

    public function test_sanea_el_nombre_como_la_migracion(): void
    {
        $this->assertSame('royal/foto_de_juan_1', $this->adapter()->recurso('\\foto de juan.1.png')[0]);
    }
}

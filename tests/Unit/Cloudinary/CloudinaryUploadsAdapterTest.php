<?php

namespace Tests\Unit\Cloudinary;

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
            ['royal/biblioteca/abc', 'image', 'https://res.cloudinary.com/nube/image/upload/royal/biblioteca/abc.jpg'],
            $this->adapter()->recurso('biblioteca/abc.jpg')
        );
    }

    public function test_pdf_en_la_raiz_va_como_image(): void
    {
        $this->assertSame(
            ['royal/x_formacion', 'image', 'https://res.cloudinary.com/nube/image/upload/royal/x_formacion.pdf'],
            $this->adapter()->recurso('x_formacion.pdf')
        );
    }

    public function test_office_va_como_raw_con_extension_en_el_public_id(): void
    {
        $this->assertSame(
            ['royal/documentos-varios/y.docx', 'raw', 'https://res.cloudinary.com/nube/raw/upload/royal/documentos-varios/y.docx'],
            $this->adapter()->recurso('documentos-varios/y.DOCX')
        );
    }

    public function test_sanea_el_nombre_como_la_migracion(): void
    {
        $this->assertSame('royal/foto_de_juan_1', $this->adapter()->recurso('\\foto de juan.1.png')[0]);
    }
}

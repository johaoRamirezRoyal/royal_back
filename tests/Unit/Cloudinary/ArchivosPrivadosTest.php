<?php

namespace Tests\Unit\Cloudinary;

use App\Services\Cloudinary\ArchivosPrivados;
use Cloudinary\Cloudinary;
use Tests\TestCase;

/**
 * Si la regla de carpetas o la firma se rompen, los archivos sensibles quedan públicos o dan
 * 401 a quien sí tiene permiso.
 */
class ArchivosPrivadosTest extends TestCase
{
    public function test_carpetas_privadas(): void
    {
        foreach ([
            'royal/Admisiones/documentos/12/cedula_ab12cd34',
            'colegio2/Admisiones/CitasPsicologia/3/firma/f_x',
            'Admisiones/documentos/cedula',
            'historia-clinica/firmas/f',
            'royal/usuarios/firmas/firma',
            'royal/carnets/uuid',
            'royal/certificados/uuid',
            'royal/gestion-humana/cursos-altura/uuid',
            'royal/formacion/certificados/uuid',
            'royal/experiencia-laboral/certificados/uuid',
            'royal/produccion-intelectual/uuid',
            'royal/permisos-licencias/uuid',
            'royal/tramites/documentos/uuid',
            'royal/recursos/documentos/uuid',
            'royal/documentos-varios/uuid.docx',
            'royal/proveedores/documentos/uuid',
            'royal/royal-school/firma_administrativa',
            'royal/royal-school/paz-salvo/sello',
        ] as $id) {
            $this->assertTrue(ArchivosPrivados::esPrivado($id), $id);
        }

        foreach ([
            'royal/carnets/plantillas/p',
            'royal/Noticias/Revistas/revista_x',
            'banner/b',
            'colegios-admision/logo',
            'royal/Instituciones/CartasRecomendacion/c',
            'royal/inventario/programacion-mant/m',
            'royal/noticias/n',
            'royal/royal-school/logotipoBackground',
            'royal/royal-school/paz-salvo/logo',
        ] as $id) {
            $this->assertFalse(ArchivosPrivados::esPrivado($id), $id);
        }
    }

    public function test_carpeta_de_colegio_que_choca_con_una_privada(): void
    {
        foreach (['certificados', 'colegio/Admisiones', 'usuarios/firmas', 'gestion-humana/'] as $carpeta) {
            $this->assertTrue(ArchivosPrivados::chocaConCarpetaPrivada($carpeta), $carpeta);
        }

        // "carnets" solo vuelve privada su raíz, que ya lo es: no choca.
        foreach (['royal', 'hebreo_union', 'colegios/sede-norte', 'carnets', 'royal-school'] as $carpeta) {
            $this->assertFalse(ArchivosPrivados::chocaConCarpetaPrivada($carpeta), $carpeta);
        }
    }

    public function test_la_firma_coincide_con_la_del_sdk(): void
    {
        $sdk = new Cloudinary(['cloud' => ['cloud_name' => 'nube', 'api_key' => 'k', 'api_secret' => 'secreto'], 'url' => ['analytics' => false]]);

        $this->assertSame(
            (string) $sdk->image('royal/carnets/a.jpg')->deliveryType('authenticated')->addTransformation('w_128,c_limit')->signUrl()->toUrl(),
            ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/carnets/a.jpg', 'w_128,c_limit')
        );
        $this->assertSame(
            (string) $sdk->raw('royal/Admisiones/x.docx')->deliveryType('authenticated')->signUrl()->toUrl(),
            ArchivosPrivados::url('nube', 'secreto', 'raw', 'royal/Admisiones/x.docx')
        );
    }

    public function test_firma_la_url_guardada_en_bd_aunque_diga_upload(): void
    {
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 'secreto']);
        $firmada = ArchivosPrivados::url('nube', 'secreto', 'image', 'royal/Admisiones/documentos/1/c_x.pdf');

        $this->assertSame($firmada, ArchivosPrivados::firmar('https://res.cloudinary.com/nube/image/upload/v1712/royal/Admisiones/documentos/1/c_x.pdf'));
        $this->assertSame($firmada, ArchivosPrivados::firmar('https://res.cloudinary.com/nube/image/authenticated/v1712/royal/Admisiones/documentos/1/c_x.pdf'));
        // Ya firmada: se vuelve a firmar igual.
        $this->assertSame($firmada, ArchivosPrivados::firmar($firmada));
    }

    public function test_no_toca_lo_publico_ni_otras_nubes(): void
    {
        config(['cloudinary.cloud_name' => 'nube', 'cloudinary.api_secret' => 'secreto']);

        foreach ([
            'https://res.cloudinary.com/nube/image/upload/v1/banner/b.png',
            'https://res.cloudinary.com/otra/image/upload/v1/royal/Admisiones/documentos/1/c.pdf',
            'royal/Admisiones/documentos/1/c.pdf',
        ] as $url) {
            $this->assertSame($url, ArchivosPrivados::firmar($url));
        }
    }
}

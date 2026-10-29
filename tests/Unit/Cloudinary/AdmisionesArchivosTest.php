<?php

namespace Tests\Unit\Cloudinary;

use App\Http\Controllers\Admissions\AdmissionsController;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Http\UploadedFile;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Archivos de admisiones en Cloudinary: no deben pisarse entre familias (antes el public_id
 * era el nombre original con overwrite en una carpeta común) y "Limpiar campos" / eliminar
 * el aspirante deben borrarlos en vez de dejarlos huérfanos. Sin BD ni Cloudinary reales.
 */
class AdmisionesArchivosTest extends TestCase
{
    private AdmissionsController $controller;

    /** Cloudinary falso: registra cada borrado y falla con el public_id "falla". */
    private CloudinaryService $nube;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nube = new class extends CloudinaryService {
            public array $borrados = [];

            public function __construct() {}

            public function deleteFile(string $publicId, string $resourceType = 'raw'): array
            {
                $this->borrados[] = "{$resourceType}:{$publicId}";

                return $publicId === 'falla' ? ['error' => true, 'message' => 'boom'] : ['error' => false];
            }
        };

        $this->controller = (new ReflectionClass(AdmissionsController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($this->controller, 'cloudinary_service'))->setValue($this->controller, $this->nube);
    }

    private function llamar(string $metodo, ...$args): mixed
    {
        return (new ReflectionMethod($this->controller, $metodo))->invoke($this->controller, ...$args);
    }

    public function test_dos_subidas_del_mismo_archivo_tienen_public_id_distinto(): void
    {
        $archivo = UploadedFile::fake()->create('cedula.pdf', 10);

        $a = $this->llamar('publicIdUnico', $archivo);
        $b = $this->llamar('publicIdUnico', $archivo);

        $this->assertNotSame($a, $b);
        $this->assertStringStartsWith('cedula_', $a);
    }

    public function test_la_carpeta_va_bajo_el_prefijo_y_por_inscripcion(): void
    {
        config(['filesystems.disks.cloudinary.prefijo' => 'royal']);
        $this->assertSame('royal/Admisiones/documentos/42', $this->llamar('carpetaAdmisiones', 'documentos/42'));

        config(['filesystems.disks.cloudinary.prefijo' => '']);
        $this->assertSame('Admisiones/documentos/42', $this->llamar('carpetaAdmisiones', 'documentos/42'));
    }

    public function test_imagenes_y_pdf_se_borran_como_image_y_office_como_raw(): void
    {
        $this->assertNull($this->llamar('borrarArchivoCloudinary', ['public_id' => 'x/foto', 'formato' => 'JPG']));
        // Los PDF se suben como "image" (CloudinaryService::getResourceType): pedirlos como raw
        // daba "not found" y el archivo nunca se borraba.
        $this->assertNull($this->llamar('borrarArchivoCloudinary', ['public_id' => 'x/acta', 'formato' => 'pdf']));
        $this->assertNull($this->llamar('borrarArchivoCloudinary', ['public_id' => 'x/carta', 'formato' => 'docx']));
        $this->assertNull($this->llamar('borrarArchivoCloudinary', ['public_id' => '', 'formato' => 'pdf']));
        $this->assertSame('boom', $this->llamar('borrarArchivoCloudinary', ['public_id' => 'falla', 'formato' => 'pdf']));

        $this->assertSame(['image:x/foto', 'image:x/acta', 'raw:x/carta', 'image:falla'], $this->nube->borrados);
    }

    public function test_los_huerfanos_se_borran_todos_aunque_uno_falle(): void
    {
        $this->llamar('borrarArchivosHuerfanos', [
            ['public_id' => 'a/1', 'formato' => 'pdf'],
            ['public_id' => 'falla', 'formato' => 'png'],
            ['public_id' => 'a/3', 'formato' => 'webp'],
        ], 9);

        $this->assertSame(['image:a/1', 'image:falla', 'image:a/3'], $this->nube->borrados);
    }
}

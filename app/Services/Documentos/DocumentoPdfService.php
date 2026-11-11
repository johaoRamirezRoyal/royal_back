<?php

namespace App\Services\Documentos;

use App\Services\branding\MarcaDominioService;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Genera en el servidor los documentos definidos en el frontend (src/documentos, pdfmake):
 * la misma definición que usa el navegador, ejecutada con Node sobre
 * resources/documentos/render.cjs (se compila con `pnpm build:documentos` en el frontend).
 * Ver docs/contexto/documentos.md.
 */
class DocumentoPdfService
{
    public function __construct(
        private MarcaDominioService $marcas,
        private CloudinaryService $cloudinary,
    ) {}

    /**
     * @param  string  $documento  id del documento en src/documentos/instituciones (ej. "cartaEntrega")
     * @param  array<string, mixed>  $datos  lo que espera ese documento
     * @param  ?string  $correo  correo del usuario: define la institución (marca y carpeta de documentos)
     * @return string el PDF en binario
     *
     * @throws RuntimeException si Node no está o el documento falla
     */
    public function generar(string $documento, array $datos, ?string $correo): string
    {
        $entrada = json_encode([
            'documento' => $documento,
            'dominio' => $this->marcas->dominioDeCorreo($correo),
            'marca' => [
                // Mismo respaldo que tenían los PDF del servidor cuando el dominio no tiene marca.
                'nombre' => $this->marcas->resolverPorCorreo($correo)['nombre'] ?: 'Royal School',
                'logo' => $this->logoComoDataUrl($correo),
            ],
            'datos' => $datos,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $proceso = Process::input($entrada)
            ->env($this->entornoNode())
            ->timeout(60)
            ->run([config('services.documentos.node', 'node'), resource_path('documentos/render.cjs')]);

        if ($proceso->failed()) {
            throw new RuntimeException("No se pudo generar el documento {$documento}: ".trim($proceso->errorOutput()));
        }

        return $proceso->output();
    }

    /**
     * En Windows, Node aborta al arrancar sin `SystemRoot` ("Assertion failed:
     * ncrypto::CSPRNG"), y el servidor web (php artisan serve, Apache) puede lanzar el proceso
     * con un entorno recortado que no la trae. Se pasa explícita; en Linux no hace falta.
     *
     * @return array<string, string>
     */
    private function entornoNode(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        return ['SystemRoot' => getenv('SystemRoot') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows')];
    }

    /**
     * Logo de la marca del dominio (o el de Royal School si no tiene) como data URL PNG/JPEG:
     * pdfmake no embebe webp, y el renderizador no descarga nada por su cuenta.
     */
    private function logoComoDataUrl(?string $correo): string
    {
        $ruta = $this->marcas->resolverRutaLocalPorCorreo($correo)
            ?? $this->cloudinary->rutaLocalRoyalSchool('logotipoBackground.png');
        $contenido = $ruta && is_file($ruta) ? file_get_contents($ruta) : false;

        if ($contenido === false) {
            throw new RuntimeException('No se pudo cargar el logo de la institución.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $imagen = @imagecreatefromstring($contenido);
            if ($imagen === false) {
                throw new RuntimeException("El logo de la institución tiene un formato no soportado ({$mime}).");
            }
            imagesavealpha($imagen, true);
            ob_start();
            imagepng($imagen);
            $contenido = (string) ob_get_clean();
            $mime = 'image/png';
        }

        return "data:{$mime};base64,".base64_encode($contenido);
    }
}

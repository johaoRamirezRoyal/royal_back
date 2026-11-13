<?php

namespace App\Services\branding;

use App\Models\Branding\ColegioAdmision;
use App\Models\Branding\Marca;
use App\Models\Branding\MarcaDominio;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Service;

/**
 * Resuelve la marca (`marcas`) a mostrar para un correo: por su dominio (`marcas_dominio`) o,
 * con sesión, por el colegio de la base de la sesión. La administración de marcas y dominios
 * está en MarcaService.
 */
class MarcaDominioService extends Service
{
    public function __construct(private CloudinaryService $cloudinaryService) {}

    /**
     * Resuelve el logo a mostrar para un correo dado — null si no hay correo, no matchea
     * ningún dominio, o la marca está desactivada (el caller cae a su logo por defecto
     * actual en cualquiera de esos casos). `url` es la URL de Cloudinary ya lista para
     * usar tal cual (frontend, o como valor de `logo_path` en UsuarioResource).
     *
     * Para incrustar el logo en un documento generado server-side (PDF/Excel) usar
     * {@see resolverRutaLocalPorCorreo} en su lugar — TCPDF/PhpSpreadsheet necesitan una
     * ruta de archivo local, no una URL remota.
     *
     * `imagen` es la imagen de referencia (fondo del friso del Login), opcional.
     *
     * Sin marca para el dominio (correos personales: gmail, hotmail... de los acudientes) y
     * con sesión, se usa la del colegio de la base de la sesión ({@see marcaDelColegioDeLaSesion}).
     * Sin sesión (vista previa del Login, comandos) no hay respaldo: el caller usa el suyo.
     *
     * @return array{url: ?string, nombre: ?string, descripcion: ?string, color: ?string, imagen: ?string}
     */
    public function resolverPorCorreo(?string $correo): array
    {
        $dominio = $this->dominioDeCorreo($correo);
        $marca = $dominio
            ? MarcaDominio::with('marca')->where('dominio', $dominio)->where('activo', true)->first()?->marca
            : null;

        if (! $marca?->activo) {
            return $this->marcaDelColegioDeLaSesion()
                ?? ['url' => null, 'nombre' => null, 'descripcion' => null, 'color' => null, 'imagen' => null];
        }

        return $this->formato($marca);
    }

    /** Ya resuelta por connection en esta petición: un listado o un PDF la pide varias veces. */
    private static array $marcasColegio = [];

    /**
     * Marca del colegio cuya base atiende la petición (`colegios_admision.connection`, la misma
     * tabla del slug de Admisiones y del portal para padres). Solo con sesión: sin ella,
     * `database.default` es `mysql` por defecto y le pondría el logo de Royal a cualquiera.
     */
    private function marcaDelColegioDeLaSesion(): ?array
    {
        if (! auth()->check()) {
            return null;
        }

        $connection = config('database.default');

        if (! array_key_exists($connection, self::$marcasColegio)) {
            $marca = ColegioAdmision::with('marca')->where('connection', $connection)->where('activo', true)->orderBy('id')->first()?->marca;
            self::$marcasColegio[$connection] = $marca?->activo ? $this->formato($marca) : null;
        }

        return self::$marcasColegio[$connection];
    }

    /** @return array{url: ?string, nombre: ?string, descripcion: ?string, color: ?string, imagen: ?string} */
    private function formato(Marca $marca): array
    {
        return ['url' => $marca->logo_path, 'nombre' => $marca->nombre, 'descripcion' => $marca->descripcion, 'color' => $marca->color, 'imagen' => $marca->imagen_fondo_path];
    }

    /**
     * Descarga a un archivo temporal local el logo resuelto para este correo — is_file()
     * (DocumentoPdfService, HorarioExcelService::agregarHoja) no verifica
     * rutas http(s) de forma confiable, así que los consumidores que arman documentos
     * necesitan un path real en disco, no la URL de Cloudinary. Null si no hay marca
     * configurada para el dominio o si la descarga falla (el caller cae a su logo por
     * defecto en ambos casos, igual que con `resolverPorCorreo`).
     */
    public function resolverRutaLocalPorCorreo(?string $correo): ?string
    {
        $url = $this->resolverPorCorreo($correo)['url'];
        if (! $url) {
            return null;
        }

        $contenido = @file_get_contents($url);
        if ($contenido === false) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
        $temporal = tempnam(sys_get_temp_dir(), 'marca').'.'.$extension;
        file_put_contents($temporal, $contenido);

        return $temporal;
    }

    /**
     * Logo del colegio para incrustar en PDF/Excel: el de la marca del dominio del correo
     * (por defecto, el del usuario autenticado) o, sin marca, el fijo de Royal School. Es el
     * único punto de entrada para logos server-side: un colegio nuevo solo configura su
     * marca en /marcas. Un WEBP se convierte a PNG porque TCPDF no lo soporta.
     */
    public function rutaLocalLogo(?string $correo = null): ?string
    {
        $ruta = $this->resolverRutaLocalPorCorreo($correo ?? auth()->user()?->correo)
            ?? $this->cloudinaryService->rutaLocalRoyalSchool('logotipoBackground.png');

        if (! $ruta || strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== 'webp') {
            return $ruta;
        }

        $imagen = @imagecreatefromwebp($ruta);
        if (! $imagen) {
            return null;
        }

        $png = tempnam(sys_get_temp_dir(), 'marca').'.png';
        imagepng($imagen, $png);
        imagedestroy($imagen);

        return $png;
    }

    /**
     * Dominio de correo normalizado (minúsculas), o null si no hay correo o no trae "@" —
     * mismo criterio que resolverPorCorreo, expuesto para consumidores que solo necesitan
     * el dominio en sí, no la marca asociada (ver LogDominioMiddleware).
     */
    public function dominioDeCorreo(?string $correo): ?string
    {
        if (! $correo || ! str_contains($correo, '@')) {
            return null;
        }

        $partes = explode('@', trim($correo));

        return mb_strtolower(end($partes));
    }
}

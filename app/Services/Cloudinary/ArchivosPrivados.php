<?php

namespace App\Services\Cloudinary;

/**
 * Archivos sensibles en Cloudinary (las carpetas de PATRON). Se suben con `type: authenticated`:
 * sin la firma `s--...--` en la URL, Cloudinary responde 401. El disco de uploads además trata
 * como privada la raíz del colegio, ver CloudinaryUploadsAdapter.
 *
 * La firma la pone el backend, nunca el frontend: al responder JSON con sesión
 * (FirmarArchivosPrivados) y en las URLs del disco. No vence: quien tenga una URL firmada la
 * abre. Lo que se gana es que solo la reparten endpoints con sesión y que no se puede armar.
 */
final class ArchivosPrivados
{
    /**
     * Sobre el public_id completo (con o sin la carpeta del colegio delante). carnets/plantillas
     * no. `certificados/` cubre también formacion/ y experiencia-laboral/certificados. Si
     * cambia, cambia también `esPrivado` en src/utils/fileUrl.ts (frontend).
     */
    private const PATRON = '#(^|/)(Admisiones|historia-clinica|usuarios/firmas|gestion-humana|certificados'
        . '|produccion-intelectual|permisos-licencias|tramites/documentos|recursos/documentos|documentos-varios|proveedores/documentos)/'
        . '|(^|/)carnets/[^/]+$'
        // Firmas y sellos fijos de royal-school (la firma de la directora en el V°B° de inventario,
        // el sello del paz y salvo de biblioteca); los logos siguen públicos.
        . '|(^|/)royal-school/(firma[^/]*|paz-salvo/sello[^/]*)$#';

    public static function esPrivado(string $publicId): bool
    {
        return (bool) preg_match(self::PATRON, $publicId);
    }

    /**
     * Si la carpeta raíz de un colegio ("Bases de datos") contiene el nombre de una carpeta
     * privada (p. ej. "certificados"), la regla volvería privado todo lo de ese colegio.
     */
    public static function chocaConCarpetaPrivada(string $carpeta): bool
    {
        return self::esPrivado(trim($carpeta, '/') . '/_/_');
    }

    /** `type` de Cloudinary para subir, borrar o renombrar este public_id. */
    public static function tipoEntrega(string $publicId): string
    {
        return self::esPrivado($publicId) ? 'authenticated' : 'upload';
    }

    /**
     * URL firmada de un asset `authenticated`. `$archivo` es el public_id con la extensión
     * (en `raw` ya la trae). La firma cubre transformación + archivo, no la versión.
     */
    public static function url(string $cloud, string $secreto, string $tipoRecurso, string $archivo, ?string $transformacion = null): string
    {
        $firmado = implode('/', array_filter([$transformacion, $archivo]));
        $firma = substr(strtr(base64_encode(sha1($firmado . $secreto, true)), '+/', '-_'), 0, 8);

        return "https://res.cloudinary.com/{$cloud}/{$tipoRecurso}/authenticated/s--{$firma}--/"
            . ($transformacion ? "{$transformacion}/" : '') . "v1/{$archivo}";
    }

    /**
     * Si `$url` es la URL de entrega de un archivo privado (como la guarda la BD: con
     * /upload/ si es de antes de la migración, o /authenticated/ sin firmar), la devuelve
     * firmada; cualquier otra cosa, tal cual. No soporta transformaciones en la URL: la BD
     * nunca las guarda.
     */
    public static function firmar(string $url): string
    {
        $cloud = (string) config('cloudinary.cloud_name');
        $patron = '#^https?://res\.cloudinary\.com/' . preg_quote($cloud, '#')
            . '/(image|raw)/(?:upload|authenticated)/(?:s--[\w-]+--/)?(?:v\d+/)?([^?\#]+)$#';

        if ($cloud === '' || ! preg_match($patron, $url, $m)) {
            return $url;
        }

        [, $tipo, $archivo] = $m;
        $ext = $tipo === 'image' ? pathinfo($archivo, PATHINFO_EXTENSION) : '';
        $publicId = $ext !== '' ? substr($archivo, 0, -strlen($ext) - 1) : $archivo;

        return self::esPrivado($publicId)
            ? self::url($cloud, (string) config('cloudinary.api_secret'), $tipo, $archivo)
            : $url;
    }
}

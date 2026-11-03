<?php

namespace App\Services\Cloudinary;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Archivos sensibles en Cloudinary (las carpetas de PATRON). Se suben con `type: authenticated`:
 * sin la firma `s--...--` en la URL, Cloudinary responde 401. El disco de uploads además trata
 * como privada la raíz del colegio, ver CloudinaryUploadsAdapter.
 *
 * La firma la pone el backend, nunca el frontend. Una URL firmada no vence (quien la tenga la
 * abre, con sesión o sin ella), así que al navegador solo llegan firmadas las fotos; los
 * documentos pasan por Laravel con sesión (paraCliente, DocumentoController).
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
     * (en `raw` ya la trae). La firma cubre transformación + archivo, no la versión. Sin `v1/`:
     * esas URLs quedaron con un 404 en el CDN durante la migración del 2026-11-03.
     */
    public static function url(string $cloud, string $secreto, string $tipoRecurso, string $archivo, ?string $transformacion = null): string
    {
        $firmado = implode('/', array_filter([$transformacion, $archivo]));
        $firma = substr(strtr(base64_encode(sha1($firmado . $secreto, true)), '+/', '-_'), 0, 8);

        return "https://res.cloudinary.com/{$cloud}/{$tipoRecurso}/authenticated/s--{$firma}--/"
            . ($transformacion ? "{$transformacion}/" : '') . $archivo;
    }

    /** URL firmada con las credenciales de config/cloudinary.php. */
    public static function firmada(string $tipoRecurso, string $archivo, ?string $transformacion = null): string
    {
        return self::url((string) config('cloudinary.cloud_name'), (string) config('cloudinary.api_secret'), $tipoRecurso, $archivo, $transformacion);
    }

    /**
     * Fotos de carnet: se ven en listas, así que van firmadas directo al CDN y no pasan por
     * Laravel. Todo lo demás privado es un documento (ver paraCliente y DocumentoController).
     */
    public static function esFoto(string $publicId): bool
    {
        return (bool) preg_match('#(^|/)carnets/[^/]+$#', $publicId);
    }

    /**
     * [tipo, archivo, public_id] si `$url` es la URL de entrega de un archivo privado (como la
     * guarda la BD: con /upload/ si es de antes de la migración, o /authenticated/ sin firmar);
     * null si no. No soporta transformaciones en la URL: la BD nunca las guarda.
     */
    private static function privadoDe(string $url): ?array
    {
        $cloud = (string) config('cloudinary.cloud_name');
        $patron = '#^https?://res\.cloudinary\.com/' . preg_quote($cloud, '#')
            . '/(image|raw)/(?:upload|authenticated)/(?:s--[\w-]+--/)?(?:v\d+/)?([^?\#]+)$#';

        if ($cloud === '' || ! preg_match($patron, $url, $m)) {
            return null;
        }

        [, $tipo, $archivo] = $m;
        $ext = $tipo === 'image' ? pathinfo($archivo, PATHINFO_EXTENSION) : '';
        $publicId = $ext !== '' ? substr($archivo, 0, -strlen($ext) - 1) : $archivo;

        return self::esPrivado($publicId) ? [$tipo, $archivo, $publicId] : null;
    }

    /** La URL de un archivo privado, firmada (para que la descargue el propio backend); cualquier otra, tal cual. */
    public static function firmar(string $url): string
    {
        $privado = self::privadoDe($url);

        return $privado ? self::firmada($privado[0], $privado[1]) : $url;
    }

    /**
     * Lo que recibe el navegador en el JSON (FirmarArchivosPrivados). Un documento va por
     * GET /api/privado/{token}/{nombre}: token cifrado (sin sesión no abre y no se puede armar a
     * mano), porque una firmada no vence y seguiría abriendo después de cerrar sesión. El nombre
     * con su extensión va al final: el frontend decide imagen o PDF por cómo termina la URL. Una
     * foto, firmada.
     */
    public static function paraCliente(string $url): string
    {
        $privado = self::privadoDe($url);
        if (!$privado) {
            return $url;
        }

        if (self::esFoto($privado[2])) {
            return self::firmada($privado[0], $privado[1]);
        }

        $token = rtrim(strtr(Crypt::encryptString("{$privado[0]}|{$privado[1]}"), '+/', '-_'), '=');

        return url("/api/privado/{$token}/" . rawurlencode(basename($privado[1])));
    }

    /** [tipo, archivo] del token de paraCliente; null si no es válido. */
    public static function deToken(string $token): ?array
    {
        try {
            [$tipo, $archivo] = explode('|', Crypt::decryptString(strtr($token, '-_', '+/')), 2) + [null, null];
        } catch (DecryptException) {
            return null;
        }

        return in_array($tipo, ['image', 'raw'], true) && $archivo ? [$tipo, $archivo] : null;
    }
}

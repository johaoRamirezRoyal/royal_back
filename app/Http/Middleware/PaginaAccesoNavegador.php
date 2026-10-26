<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando alguien abre una ruta de la API en el navegador (escribe la URL o sigue un enlace),
 * cambia el 401/403/404/503 en JSON por una página con el diseño de OMNIA.
 *
 * Va primero en el grupo `api`, así envuelve a todos los que responden esos códigos: el
 * handler de AuthenticationException, `permiso`, `sinAcceso()`, `system`, `admin.access`,
 * `institucion.session`, `ModoMantenimiento`... Una ruta que no existe no pasa por ningún
 * grupo: ese 404 lo convierte el handler de excepciones de bootstrap/app.php con `pagina()`.
 *
 * Solo actúa si el `Accept` pide `text/html` de forma explícita, como hace el navegador al
 * navegar. `apiFetch` del frontend manda `application/json` y `fetch()` sin cabeceras manda
 * `*\/*`: los dos siguen recibiendo el JSON de siempre. Por eso no se usa `acceptsHtml()`, que
 * también acepta `*\/*`.
 */
class PaginaAccesoNavegador
{
    private const AVISOS = [
        401 => [
            'tono' => 'ambar',
            'etiqueta' => 'Acceso restringido',
            'titulo' => 'Esta dirección es privada',
            'texto' => 'Forma parte de la API interna de OMNIA y solo responde a la aplicación, con una sesión iniciada. Para usar el sistema, entra desde la página de inicio de sesión.',
            'accion' => 'Iniciar sesión en OMNIA',
            'ruta' => '/',
        ],
        403 => [
            'tono' => 'rojo',
            'etiqueta' => 'Sin permiso',
            'titulo' => 'No tienes acceso a este recurso',
            'texto' => 'Tu sesión está activa, pero tu perfil no tiene el permiso que pide esta dirección. Si crees que deberías tenerlo, pídeselo al administrador del sistema.',
            'accion' => 'Volver a OMNIA',
            'ruta' => '/home',
        ],
        404 => [
            'tono' => 'neutro',
            'etiqueta' => 'No encontrada',
            'titulo' => 'No encontramos lo que buscas',
            'texto' => 'Esta dirección no existe o lo que pide ya no está disponible. Revisa que esté bien escrita o entra al sistema desde la página principal.',
            'accion' => 'Ir a OMNIA',
            'ruta' => '/',
        ],
        503 => [
            'tono' => 'ambar',
            'etiqueta' => 'En mantenimiento',
            'titulo' => 'OMNIA está en mantenimiento',
            'texto' => 'Estamos haciendo mejoras en el sistema. Vuelve a intentarlo en unos minutos.',
            'accion' => 'Volver a intentar',
            'ruta' => null, // recarga esta misma dirección
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return self::pagina($request, $next($request));
    }

    /** La página que corresponde a `$response`, o la misma respuesta si no aplica. */
    public static function pagina(Request $request, Response $response): Response
    {
        $codigo = $response->getStatusCode();
        $aviso = self::AVISOS[$codigo] ?? null;

        if (! $aviso || $request->expectsJson()
            || ! str_contains(strtolower($request->header('Accept', '')), 'text/html')) {
            return $response;
        }

        $frontend = rtrim(config('app.frontend_url'), '/');
        $headers = ['X-Robots-Tag' => 'noindex'];
        if ($response->headers->has('Retry-After')) {
            $headers['Retry-After'] = $response->headers->get('Retry-After');
        }

        return response()->view('errores.acceso', [
            ...$aviso,
            'codigo' => $codigo,
            'url' => $aviso['ruta'] === null ? $request->fullUrl() : $frontend.$aviso['ruta'],
            'logo' => $frontend.'/pwa/icon-192.png',
        ], $codigo, $headers);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Cloudinary\ArchivosPrivados;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En las respuestas JSON con sesión, reemplaza las URLs de Cloudinary de archivos privados que
 * la BD guarda sin firma (url_archivo de Admisiones, firmas...): un documento por la URL de
 * GET /api/privado, una foto por la firmada (ArchivosPrivados::paraCliente). Así el permiso
 * para ver el archivo es el del endpoint que lo lista, y ningún módulo tiene que acordarse.
 * Sin sesión no se toca: la URL sale tal cual y Cloudinary responde 401 (fail-closed).
 */
class FirmarArchivosPrivados
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse
            && str_contains((string) $response->getContent(), 'res.cloudinary.com')
            && auth('api')->check()) {
            // getData() sin `true`: los objetos quedan como stdClass y un {} vacío no pasa a [].
            $response->setData($this->firmar($response->getData()));
        }

        return $response;
    }

    private function firmar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return ArchivosPrivados::paraCliente($valor);
        }

        if (is_array($valor) || $valor instanceof \stdClass) {
            foreach ($valor as $clave => $hijo) {
                is_array($valor) ? $valor[$clave] = $this->firmar($hijo) : $valor->{$clave} = $this->firmar($hijo);
            }
        }

        return $valor;
    }
}

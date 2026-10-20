<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Usuarios\PermisosPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chequeo de permiso al inicio de un método: el mismo que el middleware `permiso` de su ruta,
 * para que el controller no dependa de la ruta. Lee las opciones que PermisosPerfil ya cargó
 * en la petición (sin otra consulta).
 *
 *   if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO, Permiso::PRESTAMOS_INVENTARIO)) {
 *       return $rechazo;
 *   }
 */
trait VerificaPermisos
{
    /** null si el perfil tiene alguna de las opciones; si no, la respuesta 403. */
    protected function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        return PermisosPerfil::tiene($request, ...$opciones)
            ? null
            : $this->error('No tienes permiso para esta acción', 403);
    }
}

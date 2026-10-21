<?php

namespace App\Services\Usuarios;

use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Opciones (cron_opciones) otorgadas al perfil del usuario autenticado, cargadas con UNA
 * consulta por petición y guardadas en el request: el middleware `permiso` y el controller
 * que lo necesite leen la misma lista, en vez de una consulta por opción. También la expone al
 * frontend (GET /usuarios/mis-permisos). Criterio: permiso activo cuya opción y módulo existen.
 */
final class PermisosPerfil
{
    private const ATRIBUTO = 'permisos_perfil';

    /** @return array<int, true> id_opcion => true */
    private static function de(Request $request): array
    {
        if (! $request->attributes->has(self::ATRIBUTO)) {
            $perfil = $request->user()?->perfil;

            $opciones = match (true) {
                $perfil === null => [],
                // Super Admin tiene TODAS las opciones existentes, otorgadas o no en
                // cron_permisos, menos las que restringen en vez de dar acceso.
                (int) $perfil === Permiso::PERFIL_SUPER_ADMIN => DB::table('cron_opciones as o')
                    ->join('cron_modulos as m', 'm.id', '=', 'o.id_modulo')
                    ->whereNotIn('o.id', Permiso::OPCIONES_RESTRICTIVAS)
                    ->pluck('o.id')
                    ->all(),
                default => DB::table('cron_permisos as p')
                    ->join('cron_opciones as o', 'o.id', '=', 'p.id_opcion')
                    ->join('cron_modulos as m', 'm.id', '=', 'o.id_modulo')
                    ->where('p.id_perfil', $perfil)
                    ->where('p.activo', 1)
                    ->distinct()
                    ->pluck('p.id_opcion')
                    ->all(),
            };

            $request->attributes->set(self::ATRIBUTO, array_fill_keys(array_map('intval', $opciones), true));
        }

        return $request->attributes->get(self::ATRIBUTO);
    }

    /** @return array<int> ids de las opciones otorgadas (GET /usuarios/mis-permisos). */
    public static function opciones(Request $request): array
    {
        return array_keys(self::de($request));
    }

    public static function tiene(Request $request, int ...$opciones): bool
    {
        $otorgadas = self::de($request);

        foreach ($opciones as $opcion) {
            if (isset($otorgadas[$opcion])) {
                return true;
            }
        }

        return false;
    }
}

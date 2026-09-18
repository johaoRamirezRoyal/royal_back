<?php

namespace App\Services\inventario;

use App\Models\Inventario\Estado;
use App\Models\Inventario\Inventario;
use App\Models\Usuarios\Usuario;

/**
 * Bloque de detalle estándar para TODOS los correos de notificación de
 * Inventario (reportar, descontinuar, liberar, asignar, préstamos, solución
 * de reportes, mantenimiento preventivo) — mismos 8 campos siempre: ID,
 * descripción, categoría, área, estado al que quedó, quien realizó la
 * acción, responsable del inventario y fecha. Estático porque no depende de
 * ningún estado propio, solo de los modelos que recibe — así lo usan tanto
 * InventarioServices como PrestamosService sin tener que inyectarse mutuamente.
 */
class InventarioEmailHelper
{
    public static function nombreUsuario(?int $idUser): ?string
    {
        if (!$idUser) {
            return null;
        }

        $usuario = Usuario::find($idUser);

        return $usuario ? trim("{$usuario->nombre} {$usuario->apellido}") : null;
    }

    public static function nombreEstado(int $idEstado, string $fallback): string
    {
        return Estado::find($idEstado)?->nombre ?? $fallback;
    }

    public static function detalle(
        Inventario $item,
        string $estadoLabel,
        ?string $actorNombre,
        ?string $responsableNombre,
        ?string $fecha = null
    ): string {
        // `loadMissing` (no `load`): si el caller ya refrescó/precargó el ítem (p. ej.
        // tras un bulk update que cambió su área/categoría), no lo vuelve a pisar.
        $item->loadMissing(['categoria', 'area']);

        return "ID: {$item->id}\n"
            . "Descripción: {$item->descripcion}\n"
            . "Categoría: " . ($item->categoria?->nombre ?? '—') . "\n"
            . "Área: " . ($item->area?->nombre ?? '—') . "\n"
            . "Estado: {$estadoLabel}\n"
            . "Realizado por: " . ($actorNombre ?? '—') . "\n"
            . "Responsable del inventario: " . ($responsableNombre ?? '—') . "\n"
            . "Fecha: " . ($fecha ?? now()->format('d/m/Y H:i'));
    }
}

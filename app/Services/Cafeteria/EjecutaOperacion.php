<?php

namespace App\Services\Cafeteria;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corre una operación en una transacción y la convierte al array estándar de los services:
 * una ReglaCafeteria sale con su status (422, o 404 si algo no existe) y su mensaje; cualquier
 * otro error, con el suyo.
 */
trait EjecutaOperacion
{
    /** @param callable(): mixed $fn */
    protected function operacion(callable $fn, string $mensaje): array
    {
        try {
            return ['error' => false, 'message' => $mensaje, 'data' => DB::transaction($fn)];
        } catch (ReglaCafeteria $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'status' => $e->status];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

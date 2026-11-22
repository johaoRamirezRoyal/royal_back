<?php

namespace App\Services\Cafeteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Códigos que asigna el sistema (nadie los escribe ni los cambia): consecutivos con prefijo
 * (CAF-001, PV-001…) o, para las funciones, derivados del nombre (AUXILIAR_DE_CAJA). El UNIQUE de
 * la base es la garantía: si otra petición toma el mismo código a la vez, se reintenta con el
 * siguiente.
 */
trait GeneraCodigo
{
    private const INTENTOS = 3;

    /**
     * Crea el registro con el siguiente código `PREFIJO-NNN` dentro de `$alcance` (la tabla
     * entera, o los hijos de un mismo padre si el código es único por padre).
     *
     * @param  Builder<Model>  $alcance
     * @param  callable(string): Model  $crear
     */
    protected function crearConConsecutivo(Builder $alcance, string $prefijo, callable $crear, int $ancho = 3): Model
    {
        return $this->reintentando(fn () => $crear($this->siguienteConsecutivo(clone $alcance, $prefijo, $ancho)));
    }

    /**
     * Crea el registro con un código sacado del nombre (mayúsculas, sin tildes, palabras con
     * guion bajo). Si ya existe, le agrega _2, _3…
     *
     * @param  Builder<Model>  $alcance
     * @param  callable(string): Model  $crear
     */
    protected function crearConCodigoDeNombre(Builder $alcance, string $nombre, int $largo, callable $crear, string $respaldo = 'FUNCION'): Model
    {
        $base = Str::of($nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit($largo - 3, '')->trim('_')->value() ?: $respaldo;

        return $this->reintentando(function () use ($alcance, $base, $crear) {
            $usados = (clone $alcance)->where('codigo', 'like', "{$base}%")->pluck('codigo')->all();
            $codigo = $base;
            for ($n = 2; in_array($codigo, $usados, true); $n++) {
                $codigo = "{$base}_{$n}";
            }

            return $crear($codigo);
        });
    }

    /** @param  Builder<Model>  $alcance */
    private function siguienteConsecutivo(Builder $alcance, string $prefijo, int $ancho): string
    {
        $mayor = $alcance->where('codigo', 'like', "{$prefijo}-%")->pluck('codigo')
            ->map(fn (string $c) => preg_match('/^' . preg_quote($prefijo, '/') . '-(\d+)$/', $c, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%0' . $ancho . 'd', $prefijo, $mayor + 1);
    }

    /** @param  callable(): Model  $crear */
    private function reintentando(callable $crear): Model
    {
        for ($intento = 1; ; $intento++) {
            try {
                return $crear();
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= self::INTENTOS) {
                    throw $e;
                }
            }
        }
    }
}

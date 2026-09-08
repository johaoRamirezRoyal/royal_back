<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * El legado (ControlRecursos::solicitarTramiteControl) insertaba literalmente '0' en
 * varias columnas varchar de `tramite` cuando el campo no aplicaba para el tipo de
 * trámite elegido (ej. `mencion_certificado` en un trámite que no es Certificado
 * Laboral) — sin este cast, esas filas viejas muestran "0" en vez de quedar vacías.
 */
class NullIfZeroString implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value === '0' ? null : $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value;
    }
}

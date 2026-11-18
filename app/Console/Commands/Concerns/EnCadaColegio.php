<?php

namespace App\Console\Commands\Concerns;

use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Corre un paso de mantenimiento una vez por colegio. Cada colegio tiene su propia base y el
 * scheduler arranca con `database.default` = `mysql`, así que sin esto los comandos
 * programados solo tocaban ese colegio (p. ej. los periodos académicos vencidos de
 * `sami_hebreo` nunca se desactivaban). Mismo patrón que SincronizarRelojHikvisionCommand.
 *
 * `$paso` devuelve el arreglo de siempre de los services (`error`, `message`). Si un colegio
 * falla se informa y se sigue con los demás; el comando termina en FAILURE.
 */
trait EnCadaColegio
{
    protected function enCadaColegio(callable $paso): int
    {
        $original = Config::get('database.default');
        $fallo = false;

        foreach (BasesDatosService::connectionsConUsuarios() as $colegio) {
            Config::set('database.default', $colegio);

            try {
                $resultado = $paso($colegio);
            } catch (Throwable $e) {
                $resultado = ['error' => true, 'message' => $e->getMessage()];
            }

            $resultado['error']
                ? $this->error("{$colegio}: {$resultado['message']}")
                : $this->info("{$colegio}: {$resultado['message']}");
            $fallo = $fallo || $resultado['error'];
        }

        Config::set('database.default', $original);

        return $fallo ? self::FAILURE : self::SUCCESS;
    }
}

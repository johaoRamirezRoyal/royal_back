<?php

namespace App\Console\Commands;

use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Corre `migrate` una vez por cada colegio (BasesDatosService::connectionsConUsuarios()),
 * para no tener que replicar el DDL a mano en cada base (ver migraciones_sql.sql).
 *
 * Las migraciones ancladas a otra connection (hoy, `protected $connection = 'admin_management'`)
 * ejecutan su DDL ahí sin importar el `--database` de este loop, pero Laravel las REGISTRA como
 * corridas en la tabla `migrations` del `--database` que las disparó, no en la de
 * `admin_management` (`Migrator::runUp()` loguea siempre contra la connection del repositorio,
 * no la de la migración). Por eso, antes de migrar cada colegio,
 * `sincronizarMigracionesGlobales()` replica ese registro al resto: sin esto, el segundo y
 * tercer colegio del loop reintentarían el mismo DDL compartido y fallarían con "ya existe"
 * (pasó con `create_marcas_table` al reconciliar `sami_hebreo` a mano).
 *
 * Un colegio que falla no corta a los demás; el comando termina en FAILURE si alguno falló.
 *
 * `--status` audita en vez de migrar: corre `migrate:status` en cada colegio, para revisar
 * antes de un `--force` si hay "Pending" sospechoso (una base con el DDL ya aplicado a mano
 * alguna vez, sin pasar por esta migración). Ver AGENTS.md ("Cada institución tiene su
 * propia base") para cómo reconciliar esos casos antes de migrar.
 */
class MigrarColegiosCommand extends Command
{
    protected $signature = 'migrate:colegios {--pretend} {--step=} {--seed} {--force} {--status}';

    protected $description = 'Corre (o audita, con --status) las migraciones en la base de cada colegio';

    public function handle(): int
    {
        $fallo = false;
        $auditar = (bool) $this->option('status');
        $comando = $auditar ? 'migrate:status' : 'migrate';
        $colegios = BasesDatosService::connectionsConUsuarios();
        $globales = $this->nombresDeMigracionesGlobales();

        foreach ($colegios as $colegio) {
            $this->line("--- {$colegio} ---");

            // --pretend no debe escribir nada; --status es de solo lectura.
            if (! $auditar && ! $this->option('pretend')) {
                $this->sincronizarMigracionesGlobales($colegios, $globales);
            }

            // migrate:status solo acepta --database: el resto de opciones son de migrate.
            $opciones = $auditar ? ['--database' => $colegio] : array_filter([
                '--database' => $colegio,
                '--force' => $this->option('force'),
                '--pretend' => $this->option('pretend'),
                '--step' => $this->option('step'),
                '--seed' => $this->option('seed'),
            ], fn ($valor) => $valor !== false && $valor !== null);

            $exit = Artisan::call($comando, $opciones);

            $this->output->write(Artisan::output());
            $fallo = $fallo || $exit !== self::SUCCESS;
        }

        return $fallo ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nombres de las migraciones ancladas a otra connection (hoy solo `admin_management`, la
     * única fuera de `connectionsConUsuarios()`): su DDL corre una sola vez para toda la app,
     * nunca por colegio.
     *
     * @return list<string>
     */
    private function nombresDeMigracionesGlobales(): array
    {
        return collect(glob(database_path('migrations/*.php')))
            ->filter(fn (string $archivo) => str_contains(file_get_contents($archivo), "protected \$connection = 'admin_management'"))
            ->map(fn (string $archivo) => basename($archivo, '.php'))
            ->values()
            ->all();
    }

    /**
     * Replica a cada colegio el registro de las migraciones globales ya corridas en cualquier
     * otro, para que Laravel no reintente su DDL compartido. Nunca toca el esquema, solo la
     * tabla `migrations` de cada colegio.
     *
     * @param  list<string>  $colegios
     * @param  list<string>  $globales
     */
    private function sincronizarMigracionesGlobales(array $colegios, array $globales): void
    {
        if ($globales === []) {
            return;
        }

        $corridas = [];
        foreach ($colegios as $colegio) {
            $corridas += DB::connection($colegio)->table('migrations')
                ->whereIn('migration', $globales)
                ->pluck('migration')
                ->flip()
                ->all();
        }

        if ($corridas === []) {
            return;
        }

        foreach ($colegios as $colegio) {
            $registradas = DB::connection($colegio)->table('migrations')
                ->whereIn('migration', $globales)
                ->pluck('migration');

            $faltantes = array_diff(array_keys($corridas), $registradas->all());

            if ($faltantes === []) {
                continue;
            }

            $batch = (int) DB::connection($colegio)->table('migrations')->max('batch') + 1;

            DB::connection($colegio)->table('migrations')->insert(
                array_map(fn (string $migracion) => ['migration' => $migracion, 'batch' => $batch], array_values($faltantes))
            );
        }
    }
}

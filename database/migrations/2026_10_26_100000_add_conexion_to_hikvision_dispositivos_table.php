<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las terminales Hikvision pasan del .env (HIKVISION_HOST/HOSTS/USERNAME/PASSWORD) a esta
     * tabla, que ya existía solo para el nombre: cada fila es una conexión completa, con sus
     * propias credenciales (la contraseña cifrada con APP_KEY, cast `encrypted`).
     *
     * Importa las terminales del .env del colegio que se está migrando
     * (`migrate --database=...`, bloque `services.hikvision.legado.{connection}`). Las filas que
     * solo tenían nombre y no están en el .env quedan inactivas: hoy no se usan. Es idempotente
     * por device_id ("host:puerto").
     */
    public function up(): void
    {
        Schema::table('hikvision_dispositivos', function (Blueprint $table) {
            $table->string('protocolo', 5)->default('http')->after('nombre');
            $table->string('host', 100)->nullable()->after('protocolo');
            $table->unsignedInteger('puerto')->nullable()->after('host');
            $table->string('usuario', 100)->nullable()->after('puerto');
            $table->text('password')->nullable()->after('usuario');
            $table->boolean('principal')->default(false)->after('password');
            $table->boolean('activo')->default(true)->after('principal');
        });

        $config = config('services.hikvision.legado.' . config('database.default')) ?? [];
        $delEnv = $this->dispositivosDelEnv($config);

        DB::transaction(function () use ($config, $delEnv) {
            foreach (DB::table('hikvision_dispositivos')->get(['id', 'device_id']) as $fila) {
                [$host, $puerto] = array_pad(explode(':', $fila->device_id, 2), 2, null);
                DB::table('hikvision_dispositivos')->where('id', $fila->id)->update([
                    'host' => $host,
                    'puerto' => $puerto,
                    'activo' => isset($delEnv[$fila->device_id]),
                ]);
            }

            $password = ($config['password'] ?? '') !== '' ? Crypt::encryptString($config['password']) : null;

            foreach (array_values($delEnv) as $i => $d) {
                $existente = DB::table('hikvision_dispositivos')->where('device_id', $d['device_id'])->first();
                $datos = [
                    'nombre' => $existente->nombre ?? $d['nombre'],
                    'protocolo' => $d['protocolo'],
                    'host' => $d['host'],
                    'puerto' => $d['puerto'],
                    'usuario' => $config['username'] ?? null,
                    'password' => $password,
                    'principal' => $i === 0,
                    'activo' => true,
                ];

                $existente
                    ? DB::table('hikvision_dispositivos')->where('id', $existente->id)->update($datos)
                    : DB::table('hikvision_dispositivos')->insert(['device_id' => $d['device_id'], ...$datos]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hikvision_dispositivos', function (Blueprint $table) {
            $table->dropColumn(['protocolo', 'host', 'puerto', 'usuario', 'password', 'principal', 'activo']);
        });
    }

    /**
     * Mismo formato que leía hikvisionattendanceService: el principal (host/port/protocol) más
     * `extra_hosts` = "Nombre@host[:puerto],host2[:puerto2]" con las mismas credenciales.
     *
     * @return array<string, array{device_id: string, nombre: string, protocolo: string, host: string, puerto: int}>
     */
    private function dispositivosDelEnv(array $config): array
    {
        if (empty($config['host'])) {
            return [];
        }

        $protocolo = $config['protocol'] ?? 'http';
        $puertoDefecto = (int) ($config['port'] ?? 8000);
        $entradas = [[null, $config['host'], $puertoDefecto]];

        foreach (array_filter(array_map('trim', explode(',', (string) ($config['extra_hosts'] ?? '')))) as $entrada) {
            [$nombre, $entrada] = str_contains($entrada, '@') ? explode('@', $entrada, 2) : [null, $entrada];
            [$host, $puerto] = array_pad(explode(':', trim($entrada), 2), 2, null);
            $entradas[] = [$nombre ? trim($nombre) : null, $host, (int) ($puerto ?? $puertoDefecto)];
        }

        $dispositivos = [];
        foreach ($entradas as [$nombre, $host, $puerto]) {
            $id = "{$host}:{$puerto}";
            $dispositivos[$id] ??= ['device_id' => $id, 'nombre' => $nombre ?: $id, 'protocolo' => $protocolo, 'host' => $host, 'puerto' => $puerto];
        }

        return $dispositivos;
    }
};

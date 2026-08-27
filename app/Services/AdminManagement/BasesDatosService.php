<?php

namespace App\Services\AdminManagement;

use App\Models\BaseDatosNombre;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Lista las connections de base de datos que la app realmente usa hoy y si responden — no
 * expone host/nombre real de la base (dato sensible para un panel que puede ver más de un
 * Super Admin), solo la connection (clave interna) y un nombre visible. Ese nombre tiene un
 * default acá mismo (CONNECTIONS) pero es editable desde el panel; el override se guarda en
 * `bases_datos_nombres` (ver BaseDatosNombre). Cuando se sumen bases por tenant/dominio (ver
 * MarcaDominioService — mismo espíritu de "administración transversal" en admin_management),
 * agregarlas acá a mano.
 */
class BasesDatosService extends Service
{
    private const CONNECTIONS = [
        'mysql' => 'Base operativa (S.A.M.I)',
        'admin_management' => 'Administración (marcas por dominio, logs)',
    ];

    public function listar(): array
    {
        try {
            $nombresGuardados = BaseDatosNombre::pluck('nombre', 'connection');

            $data = [];

            foreach (self::CONNECTIONS as $connection => $nombrePorDefecto) {
                $config = config("database.connections.{$connection}", []);

                $data[] = [
                    'connection' => $connection,
                    'nombre' => $nombresGuardados[$connection] ?? $nombrePorDefecto,
                    'driver' => $config['driver'] ?? null,
                    'en_linea' => $this->probarConexion($connection),
                ];
            }

            return [
                'error' => false,
                'message' => 'Bases de datos obtenidas correctamente.',
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar las bases de datos');

            return ['error' => true, 'message' => 'Error en el servidor al listar las bases de datos.', 'data' => []];
        }
    }

    public function renombrar(string $connection, string $nombre): array
    {
        try {
            if (!array_key_exists($connection, self::CONNECTIONS)) {
                return ['error' => true, 'message' => 'Esa base de datos no existe.', 'data' => []];
            }

            BaseDatosNombre::updateOrCreate(['connection' => $connection], ['nombre' => $nombre]);

            return ['error' => false, 'message' => 'Nombre actualizado correctamente.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al renombrar la base de datos');

            return ['error' => true, 'message' => 'Error en el servidor al renombrar la base de datos.', 'data' => []];
        }
    }

    public function restablecerNombre(string $connection): array
    {
        try {
            if (!array_key_exists($connection, self::CONNECTIONS)) {
                return ['error' => true, 'message' => 'Esa base de datos no existe.', 'data' => []];
            }

            BaseDatosNombre::where('connection', $connection)->delete();

            return ['error' => false, 'message' => 'Nombre restablecido al valor por defecto.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al restablecer el nombre de la base de datos');

            return ['error' => true, 'message' => 'Error en el servidor al restablecer el nombre.', 'data' => []];
        }
    }

    private function probarConexion(string $connection): bool
    {
        try {
            DB::connection($connection)->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            // No dejar la conexión de prueba abierta — este listado se puede refrescar
            // seguido desde la UI y cada ping abre un handle nuevo si no se cierra.
            DB::disconnect($connection);
        }
    }
}

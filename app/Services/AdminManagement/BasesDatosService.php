<?php

namespace App\Services\AdminManagement;

use App\Models\BaseDatosNombre;
use App\Models\ConexionActiva;
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
        'mysql' => 'Base de datos (Royal School)',
        'sami_hebreo' => 'Base de datos de Sami (hebreo)',
        'admin_management' => 'Administración (marcas por dominio, logs)'
    ];

    /** Formato de una carpeta de Cloudinary: segmentos de [A-Za-z0-9_-] separados por "/". */
    public const PATRON_CARPETA = '/^[A-Za-z0-9_-]+(\/[A-Za-z0-9_-]+)*$/';

    /** Carpeta ya resuelta por connection en esta petición: el disco la pide por cada archivo. */
    private static array $carpetas = [];

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
                    'carpeta_cloudinary' => self::carpeta($connection),
                    'carpeta_por_defecto' => self::carpetaPorDefecto($connection),
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

            // Solo el nombre: la fila puede guardar también la carpeta de Cloudinary.
            BaseDatosNombre::where('connection', $connection)->update(['nombre' => null]);

            return ['error' => false, 'message' => 'Nombre restablecido al valor por defecto.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al restablecer el nombre de la base de datos');

            return ['error' => true, 'message' => 'Error en el servidor al restablecer el nombre.', 'data' => []];
        }
    }

    /**
     * Carpeta raíz del colegio en Cloudinary: sus archivos van a `<carpeta>/<ruta>` (disco
     * `cloudinary`, Admisiones y las subidas de los módulos). Se edita en "Bases de datos";
     * sin valor guardado, la de fábrica.
     */
    public static function carpeta(string $connection): string
    {
        if (!isset(self::$carpetas[$connection])) {
            try {
                $guardada = BaseDatosNombre::where('connection', $connection)->value('carpeta_cloudinary');
            } catch (Exception) {
                $guardada = null; // admin_management caída: no tumbar las subidas, usar la de fábrica
            }
            self::$carpetas[$connection] = $guardada ?: self::carpetaPorDefecto($connection);
        }

        return self::$carpetas[$connection];
    }

    /** `mysql` (Royal School) conserva su carpeta histórica, "royal" (la de la migración de SAMI); las demás, el nombre de su connection. */
    public static function carpetaPorDefecto(string $connection): string
    {
        return $connection === 'mysql' ? 'royal' : $connection;
    }

    /** `$carpeta` null = volver a la de fábrica. Solo cambia dónde van los archivos nuevos: los ya subidos no se mueven. */
    public function cambiarCarpeta(string $connection, ?string $carpeta): array
    {
        try {
            if (!array_key_exists($connection, self::CONNECTIONS)) {
                return ['error' => true, 'message' => 'Esa base de datos no existe.', 'data' => []];
            }

            // El formato ya lo validó el controller (PATRON_CARPETA). Vacía = la de fábrica.
            BaseDatosNombre::updateOrCreate(['connection' => $connection], ['carpeta_cloudinary' => $carpeta ?: null]);
            unset(self::$carpetas[$connection]);

            return ['error' => false, 'message' => 'Carpeta de archivos actualizada.', 'data' => ['carpeta_cloudinary' => self::carpeta($connection)]];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar la carpeta de Cloudinary');

            return ['error' => true, 'message' => 'Error en el servidor al cambiar la carpeta.', 'data' => []];
        }
    }

    /** Usado también por SwitchActiveConnection para no confiar en un valor arbitrario de
     * `admin_conexion_activa` como `database.default` — fail-closed ante datos corruptos. */
    /** Nombre de la institución de una connection: el editado en "Bases de datos" o el de fábrica. */
    public static function nombre(string $connection): string
    {
        try {
            $guardado = BaseDatosNombre::where('connection', $connection)->value('nombre');
        } catch (Exception) {
            $guardado = null; // admin_management caída: no tumbar a quien solo quiere el nombre
        }

        return $guardado ?? self::CONNECTIONS[$connection] ?? $connection;
    }

    public static function esConnectionValida(string $connection): bool
    {
        return array_key_exists($connection, self::CONNECTIONS);
    }

    /** Connections que representan un tenant real con su propia tabla `usuarios` — usado por
     * el login multi-tenant (AuthServices::resolverUsuarioMultiTenant) para saber en qué
     * bases buscar credenciales. Excluye `admin_management`, que es transversal (marcas por
     * dominio, logs) y no tiene usuarios propios. */
    public static function connectionsConUsuarios(): array
    {
        return array_values(array_diff(array_keys(self::CONNECTIONS), ['admin_management']));
    }

    /**
     * Connection que este usuario eligió como `database.default` para el resto de la app
     * (ver SwitchActiveConnection) — `mysql` (la operativa) si nunca eligió una o si el
     * valor guardado ya no es una connection conocida.
     */
    public function conexionActiva(int $idUser): string
    {
        $connection = ConexionActiva::find($idUser)?->connection;

        return $connection && self::esConnectionValida($connection) ? $connection : 'mysql';
    }

    public function establecerConexionActiva(int $idUser, string $connection): array
    {
        try {
            if (!self::esConnectionValida($connection)) {
                return ['error' => true, 'message' => 'Esa base de datos no existe.', 'data' => []];
            }

            ConexionActiva::updateOrCreate(['id_user' => $idUser], ['connection' => $connection]);

            return ['error' => false, 'message' => 'Base de datos activa actualizada.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al establecer la base de datos activa');

            return ['error' => true, 'message' => 'Error en el servidor al establecer la base de datos activa.', 'data' => []];
        }
    }

    public function restablecerConexionActiva(int $idUser): array
    {
        try {
            ConexionActiva::where('id_user', $idUser)->delete();

            return ['error' => false, 'message' => 'Base de datos activa restablecida a la operativa.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al restablecer la base de datos activa');

            return ['error' => true, 'message' => 'Error en el servidor al restablecer la base de datos activa.', 'data' => []];
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

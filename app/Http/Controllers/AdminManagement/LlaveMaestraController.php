<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\LlaveMaestraService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Generación e historial de llaves de acceso de soporte — ver LlaveMaestraService para la
 * lógica. El canje en sí (AuthController::redeemMasterKey) es público, no vive acá: quien
 * canjea la llave todavía no tiene sesión.
 */
class LlaveMaestraController extends Controller implements HasMiddleware
{
    /**
     * Solo Super Admin con correo en la allowlist de administración (RestrictToAdminEmails),
     * igual que su ruta: el controller no depende de que la ruta lo tenga.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('admin.access'),
            new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN])),
        ];
    }

    public function __construct(
        private LlaveMaestraService $service,
    ) {
    }

    public function index()
    {
        return response()->json(['error' => false, 'data' => $this->serializar($this->service->listar())]);
    }

    /** Sesiones abiertas con llave que siguen en curso (ver LlaveMaestraService::sesionesAbiertas). */
    public function sesiones()
    {
        $ttl = (int) config('jwt.ttl');

        $data = $this->serializar($this->service->sesionesAbiertas())
            ->map(fn (array $fila) => $fila + ['vence_en' => $fila['usado_en']->copy()->addMinutes($ttl)]);

        return response()->json(['error' => false, 'data' => $data]);
    }

    /** Cierra la sesión abierta con la llave $id: su siguiente petición recibe 401 (ver JwtFromCookie). */
    public function cerrarSesion(int $id)
    {
        if (!$this->service->cerrarSesionDeLlave($id)) {
            return $this->error('Esa sesión ya no está abierta.', 404);
        }

        return $this->success('Sesión cerrada correctamente');
    }

    private function serializar(Collection $llaves): Collection
    {
        // Resueltos por connection para no repetir la misma consulta por cada fila —
        // el volumen de este historial es chico (una acción manual, no algo masivo).
        $usuariosPorConnection = [];

        return $llaves->map(function ($llave) use (&$usuariosPorConnection) {
            $connection = $llave->connection_objetivo;
            $usuariosPorConnection[$connection] ??= [];

            $resolver = function (int $id) use ($connection, &$usuariosPorConnection) {
                if (!array_key_exists($id, $usuariosPorConnection[$connection])) {
                    $usuario = Usuario::on($connection)->find($id);
                    $usuariosPorConnection[$connection][$id] = $usuario
                        ? ['nombre' => $usuario->nombre, 'apellido' => $usuario->apellido, 'correo' => $usuario->correo]
                        : null;
                }

                return $usuariosPorConnection[$connection][$id];
            };

            return [
                'id' => $llave->id,
                'usuario_objetivo' => $resolver($llave->id_user_objetivo),
                'generado_por' => $resolver($llave->generado_por),
                'connection' => $connection,
                'usado_en' => $llave->usado_en,
                'ip_uso' => $llave->ip_uso,
                'expira_en' => $llave->expira_en,
                'created_at' => $llave->created_at,
            ];
        });
    }

    public function generar(Request $request)
    {
        $request->validate(
            ['id_user' => 'required|integer'],
            ['id_user.required' => 'El usuario destino es obligatorio.']
        );

        $resultado = $this->service->generar($request->integer('id_user'), config('database.default'), $request->user()->id_user);

        if (!$resultado) {
            return $this->error('El usuario destino no existe o no está activo.', 404);
        }

        return $this->success('Llave generada correctamente', [
            'key' => $resultado['key'],
            'expira_en' => $resultado['expira_en'],
        ]);
    }

    public function revocar(int $id)
    {
        if (!$this->service->revocar($id)) {
            return $this->error('La llave no existe o ya no se puede revocar (usada o expirada).', 404);
        }

        return $this->success('Llave revocada correctamente');
    }
}

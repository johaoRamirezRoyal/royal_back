<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\LlaveMaestraService;
use Illuminate\Http\Request;

/**
 * Generación e historial de llaves de acceso de soporte — ver LlaveMaestraService para la
 * lógica. El canje en sí (AuthController::redeemMasterKey) es público, no vive acá: quien
 * canjea la llave todavía no tiene sesión.
 */
class LlaveMaestraController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private LlaveMaestraService $service,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para gestionar llaves maestras', 403));
        }
    }

    public function index()
    {
        $llaves = $this->service->listar();

        // Resueltos por connection para no repetir la misma consulta por cada fila —
        // el volumen de este historial es chico (una acción manual, no algo masivo).
        $usuariosPorConnection = [];

        $data = $llaves->map(function ($llave) use (&$usuariosPorConnection) {
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

        return response()->json(['error' => false, 'data' => $data]);
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

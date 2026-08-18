<?php
namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Http\Requests\LlegadasTarde\LlegadaTardeRequest;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;

class LlegadasTardeController extends Controller
{
    // Opción "Gestión Académica" (/permisos): quien la tiene ve/gestiona el módulo
    // completo (cualquier fecha, eliminar, dashboard). Sin ella pero con acceso al
    // módulo (opción 101, ver migración 2026_08_18_100000) el acceso es restringido:
    // solo hoy, sin eliminar ni dashboard. Para dar ese mismo acceso restringido a un
    // perfil nuevo alcanza con otorgarle la opción 101 desde /permisos, sin tocar código.
    private const OPCION_ACCESO_COMPLETO = 99;

    public function __construct(
        private LlegadasTarde $llegadas_tarde,
        private UsuariosServices $usuariosService,
    ) {}

    private function tieneAccesoCompleto(Request $request): bool
    {
        return $this->usuariosService->tienePermiso(self::OPCION_ACCESO_COMPLETO, $request->user()->perfil)['permiso'] ?? false;
    }

    public function agregarLlegadaTarde(LlegadaTardeRequest $request){
        $body = $request->validated();

        $id_alumno = $body['id_alumno'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];

        $response = $this->llegadas_tarde->agregarLlegadaTarde($id_alumno, $fecha, $hora);

        return $this->apiResponse($response);
    }

    public function obtenerLlegadasTarde(Request $request){

        $id_anio_academico = $request->input('id_periodo_academico', null);
        $id_alumno = $request->input('id_alumno', null);

        // Sin acceso completo, solo se pueden ver las llegadas tarde del día actual: se
        // ignora cualquier `fecha` que mande el cliente y se fuerza hoy.
        $fecha = $this->tieneAccesoCompleto($request)
            ? $request->input('fecha', null)
            : now()->toDateString();

        $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, $id_alumno, $fecha);

        return $this->apiResponse($response);

    }

    public function dashboardLlegadasTarde(Request $request){
        if (!$this->tieneAccesoCompleto($request)) {
            return $this->error('No tienes permiso para ver el dashboard de llegadas tarde', 403);
        }

        $id_periodo_academico = $request->input('id_periodo_academico', null);

        $response = $this->llegadas_tarde->dashboardLlegadasTarde($id_periodo_academico);

        return $this->apiResponse($response);
    }

    public function eliminarLlegadaTarde(Request $request, ?int $id = null){
        if (!$this->tieneAccesoCompleto($request)) {
            return $this->error('No tienes permiso para eliminar llegadas tarde', 403);
        }

        $ids = $id !== null ? [$id] : $request->input('ids_llegadas_tarde', []);

        $response = $this->llegadas_tarde->eliminarLlegadaTarde($ids);

        return $this->apiResponse($response);
    }

    // Reenviar correo es una acción operativa (como registrar la llegada tarde), no
    // administrativa: no requiere acceso completo, cualquiera con acceso al módulo
    // (99 o 101) puede reintentar la notificación.
    public function reenviarCorreo(int $id){
        $response = $this->llegadas_tarde->reenviarCorreo($id);

        return $this->apiResponse($response);
    }
}

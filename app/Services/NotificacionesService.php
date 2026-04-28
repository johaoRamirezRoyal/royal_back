<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\Log;

class NotificacionesService
{
    /**
     * Crea una notificación general.
     * @param int $user_id
     * @param string $title
     * @param string $message
     * @param int $perfil
     * @param string $level
     * @param string $type
     * @return array{data: Notificacion, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function crear(
        int $user_id,
        string $title,
        string $message,
        int $perfil = 1,
        string $level = 'info',
        string $type = 'sistemas'
    ): array {
        try {
            $notificacion = Notificacion::create([
                'user_id' => $user_id,
                'title' => $title,
                'message' => $message,
                'perfil' => $perfil,
                'level' => $level,
                'type' => $type,
            ]);

            return [
                'error' => false,
                'message' => "Notificación creada",
                'data' => $notificacion,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => "Error al crear la notificación: " . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Crear multiples notificaciones para un array de usuarios
     * @param array $userIds
     * @param string $title
     * @param string $message
     * @param int $perfil
     * @param string $level
     * @param string $type
     * @return array
     */
    public function crearParaMultiplesUsuario(
        array $userIds,
        string $title,
        string $message,
        int $perfil = 1,
        string $level = 'info',
        string $type = 'sistemas'
    ): array {
        foreach ($userIds as $user) {
            $notificacion = $this->crear($user, $title, $message, $perfil, $level, $type);
        }

        return $notificacion;
    }

    /**
     * Crear notificación a partir de un perfil
     * @param int $perfilTarget
     * @param string $title
     * @param string $message
     * @param string $level
     * @param string $type
     * @return array
     */
    public function crearPorPerfil(
        int $perfilTarget,
        string $title,
        string $message,
        string $level = 'info',
        string $type = 'sistemas',
    ): array {
        $usuarios = Usuario::where('perfil', $perfilTarget)->pluck('id_user');
        if ($usuarios->isEmpty()) {
            return [
                'error' => true,
                'message' => "No hay usuarios disponibles para ese perfil",
                'data' => [],
            ];
        }
        $notificaciones = $this->crearParaMultiplesUsuario($usuarios->toArray(), $title, $message, $perfilTarget, $level, $type);

        return $notificaciones;
    }

    /**
     * Summary of marcarComoLeida
     * @param int $notificacionId
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function marcarComoLeida(int $notificacionId)
    {
        try {
            Notificacion::find($notificacionId)?->update(['read_at' => now()]);

            return [
                'error' => false,
                'message' => "Notificaciones leídas",
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error("Error al marcar todas las notificaciones como leídas: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "Error al leer todas las notificaciones: " . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Summary of marcarTodasComoLeidas
     * @param int $id_user
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function marcarTodasComoLeidas(int $id_user): array
    {
        try {
            Notificacion::where('user_id', $id_user)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return [
                'error' => false,
                'message' => "Notificaciones leídas",
                'data' => Notificacion::where('user_id', $id_user)->whereNull('read_at')->get()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error("NO SE MARCARON COMO LEIDAS LAS NOTIFICACIONES: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "Notificaciones no leídas: " . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Eliminar notificación de la base de datos.
     * @param int $id_notificacion
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function eliminarNotificacion(int $id_notificacion){
        try{
            $notificacion = Notificacion::find($id_notificacion);

            if (!$notificacion) {
                return [
                    'error' => true,
                    'message' => 'Notificación no encontrada',
                    'data' => null,
                ];
            }

            $notificacion->delete();

            return [
                'error' => false,
                'message' => 'Notificación eliminada',
                'data' => $notificacion, // ← antes de eliminar
            ];
        }catch(\Exception $e){
            Log::error("NO SE ELIMINO LA NOTIFICACIÓN: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "No se puedo eliminar la notificación" . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}

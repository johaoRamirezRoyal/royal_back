<?php
namespace App\Services\Permisos;

use App\Models\Perfil;
use App\Models\Permiso;

class PermisosService
{


    public function crearPermiso($datos)
    {
        $id_opcion = $datos['id_opcion'];
        $id_perfil = $datos['id_perfil'];
        $user_log = $datos['user_log'];
        $activo = 1;

        try {
            $existe = Permiso::where('id_opcion', $id_opcion)
                ->where('id_perfil', $id_perfil)
                ->exists();
            if ($existe) {
                return [
                    'error' => false,
                    'message' => 'El permiso ya existe'
                ];
            }

            Permiso::create([
                'id_opcion' => $id_opcion,
                'id_perfil' => $id_perfil,
                'user_log' => $user_log,
                'activo' => $activo,
                'fechareg' => now()
            ]);

            return [
                'error' => false,
                'message' => 'Permiso creado'
            ];
        } catch (\Exception $ex) {
            return [
                'error' => true,
                'message' => $ex->getMessage()
            ];
        }
    }

    public function cambiarEstadoPermiso($datos)
    {
        $id_opcion = $datos['id_opcion'];
        $activo = $datos['activo'];

        try {

            $actualizado = Permiso::where('id', $id_opcion)
                ->update(['activo' => $activo]);

            if ($actualizado === 0) {
                return [
                    'error' => true,
                    'message' => 'El permiso no existe'
                ];
            }

            return [
                'error' => false,
                'message' => 'Se ha cambiado el estado correctamente'
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function verPermisosPorPerfil($id_perfil){
        try{
            $perfil = Perfil::with([
                'opciones' => function($query){
                    $query->with('modulo');
                }
            ])->find($id_perfil);

            if(!$perfil){
                return [
                    'error' => false,
                    'message' => 'Perfil no existe',
                ];
            }
            return [
                'error'=> false,
                'data' => $perfil
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}

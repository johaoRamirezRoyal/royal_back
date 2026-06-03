<?php
namespace App\Services\Permisos;


use App\Models\PermisosAutorizaciones\Opcion;
use App\Models\PermisosAutorizaciones\Permiso;
use App\Models\Usuarios\Perfil;
use App\Services\Service;

class PermisosService extends Service
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

            $creado = Permiso::create([
                'id_opcion' => $id_opcion,
                'id_perfil' => $id_perfil,
                'user_log' => $user_log,
                'activo' => $activo,
                'fechareg' => now()
            ]);

            return [
                'error' => false,
                'message' => 'Permiso creado',
                'data' => $creado->toArray()
            ];
        } catch (\Exception $ex) {
            $this->sendError($ex, 'Error al crear el permiso');
            return [
                'error' => true,
                'message' => $ex->getMessage(),
                'data' => $datos
            ];
        }
    }

    /**
     * Metodo para eliminar un permiso en base a la opcion y el perfil.
     * @param array $datos
     * @return array{data: array, error: bool, message: string}
     */
    public function eliminarPermiso(array $datos) : array
    {
        $id_opcion = $datos['id_opcion'];
        $id_perfil = $datos['id_perfil'];

        try {
            $permiso = Permiso::where('id_opcion', $id_opcion)
                ->where('id_perfil', $id_perfil)
                ->first();

            if (!$permiso) {
                return [
                    'error' => true,
                    'message' => 'El permiso no existe',
                    'data' => []
                ];
            }

            $permiso->delete();

            return [
                'error' => false,
                'message' => 'Permiso eliminado',
                'data' => $permiso->toArray()
            ];
        } catch (\Exception $ex) {
            $this->sendError($ex, "error al eliminar el permiso");
            return [
                'error' => true,
                'message' => $ex->getMessage(),
                'data' => $datos
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

    public function verPermisosActivosPorPerfil($id_perfil){
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

    public function verOpcionesPorPerfil(?array $id_perfil = null)
    {
        try {
            $perfiles = !empty($id_perfil)
                ? Perfil::whereIn('id_perfil', $id_perfil)->get()
                : Perfil::all();

            $opciones = Opcion::with('modulo')->orderBy('id_modulo')->get();

            $data = [];
            foreach ($perfiles as $perfil) {
                $permisos = Permiso::where('id_perfil', $perfil->id_perfil)
                    ->get()
                    ->keyBy('id_opcion');

                $opcionesData = [];
                
                foreach ($opciones as $opcion) {
                    $permiso = $permisos->get($opcion->id);
                    $opcionesData[] = [
                        'id' => $opcion->id,
                        'nombre' => $opcion->nombre,
                        'modulo' => $opcion->modulo ? [
                            'id' => $opcion->modulo->id,
                            'nombre' => $opcion->modulo->nombre,
                        ] : null,
                        'activa' => $permiso ? (bool) $permiso->activo : false,
                        'permiso_id' => $permiso ? $permiso->id : null,
                    ];
                }

                $data[] = [
                    'perfil' => [
                        'id' => $perfil->id_perfil,
                        'nombre' => $perfil->nombre,
                    ],
                    'opciones' => $opcionesData,
                ];
            }

            return [
                'error' => false,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function verPermisosOpciones(){
        try{
            $permisos =Opcion::with(['perfiles', 'modulo'])->get();

            $data = $permisos->map(function ($item) {
                return [
                    'opcion' => [
                        'id' => $item->id,
                        'nombre' => $item->nombre,
                        'activo' => $item->activo,
                        'fechareg' => $item->fechareg,
                        'modulo' => $item->modulo,
                    ],
                    'perfiles' => $item->perfiles
                ];
            });

            return [
                'error' => false,
                'data' => $data
            ];

        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}

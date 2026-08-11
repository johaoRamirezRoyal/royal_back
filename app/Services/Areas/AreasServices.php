<?php

namespace App\Services\Areas;

use App\Models\Areas\Areas;
use App\Models\Inventario\Inventario;
use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\DB;
use App\Services\Service;

class AreasServices extends Service
{
    public function crearArea($datos)
    {
        $nombre = $datos["nombre"];
        $user_log = $datos["user_log"];

        try {
            $area_nueva = Areas::create([
                "nombre" => $nombre,
                "user_log" => $user_log,
                "fechareg" => now()
            ])->latest("id")->first();

            return [
                "error" => false,
                "data" => $area_nueva->toArray()
            ];
        } catch (\Exception $e) {
            return [
                "error" => true,
                "message" => $e->getMessage()
            ];
        }
    }

    public function obtenerTodasLasAreas()
    {
        try {
            $areas = Areas::get();
            return [
                'error' => false,
                'data' => $areas
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function actualizarArea($id, $datos){
        try {
            $area = Areas::find($id);

            if (!$area) {
                return [
                    'error' => true,
                    'message' => 'Área no encontrada'
                ];
            }

            $area->update($datos);

            return [
                'error' => false,
                'message' => 'Área actualizada correctamente'
            ];

        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function filtrarAreas($filtro){
        try{
            $areas = Areas::where('nombre', 'like', '%' . $filtro . '%')
                        ->get();

            if($areas->isEmpty()){
                return [
                    'error' => false,
                    'message' => 'No se encontraron áreas con el filtro proporcionado',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Áreas encontradas',
                'data' => $areas,
            ];

        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function desactivarAreas(array $ids, int $estado)
    {
        try {
            Areas::whereIn('id', $ids)->update(['activo' => $estado]);
            return [
                'error' => false,
                'message' => 'Áreas actualizadas correctamente'
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function asignarArea(int $id_user, int $id_area)
    {
        try {
            $inventario = Inventario::where('id_area', $id_area)
                                ->update(['id_user' => $id_user]);

            if($inventario === 0){
                return [
                    'error' => true,
                    'message' => 'No se puedo asignar el area'
                ];
            }

            return [
                'error' => false,
                'message' => 'Área Asignada correctamente'
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function usuariosAsignables()
    {
        try {
            $usuarios = Usuario::where('estado', 'activo')
                ->whereNotIn('perfil', [6, 16, 17, 28])
                ->select('id_user', DB::raw("CONCAT(nombre, ' ', apellido) AS nom_user"), 'perfil')
                ->orderBy('nombre')
                ->get();

            return [
                'error' => false,
                'data' => $usuarios
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function usuarioResponsableArea(int $id_area): array{
        try {
            $responsables = Inventario::where('id_area', $id_area)
                            ->whereNotNull('id_user')
                            ->with('usuario:id_user,nombre,apellido')
                            ->get()
                            ->pluck('usuario')
                            ->filter()
                            ->unique('id_user')
                            ->values();

            return [
                'error' => false,
                'data' => $responsables
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function usuariosResponsablesAreas(array $ids): array
    {
        try {
            $responsables = Inventario::whereIn('id_area', $ids)
                            ->whereNotNull('id_user')
                            ->with('usuario:id_user,nombre,apellido')
                            ->get()
                            ->groupBy('id_area')
                            ->map(function ($items) {
                                return $items->pluck('usuario')->filter()->unique('id_user')->values();
                            });

            return [
                'error' => false,
                'data' => $responsables
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Services\Areas;

use App\Models\Areas\Bloque;
use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\DB;
use App\Services\Service;

class BloquesServices extends Service
{
    /** Perfiles que pueden ser responsables de un bloque: asistente de nivel (11), coordinador (26). */
    private const PERFILES_RESPONSABLES = [11, 26];

    public function crearBloque(array $datos)
    {
        try {
            $bloque = Bloque::create([
                "nombre" => $datos["nombre"],
                "id_nivel" => $datos["id_nivel"] ?? null,
                "user_log" => $datos["user_log"],
                "fechareg" => now(),
            ]);

            return [
                "error" => false,
                "data" => $bloque->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                "error" => true,
                "message" => $e->getMessage(),
            ];
        }
    }

    public function actualizarBloque($id, $datos)
    {
        try {
            $bloque = Bloque::find($id);

            if (!$bloque) {
                return [
                    'error' => true,
                    'message' => 'Bloque no encontrado',
                ];
            }

            $bloque->update($datos);

            return [
                'error' => false,
                'message' => 'Bloque actualizado correctamente',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param int|null $idNivel Si viene, limita el listado a bloques de ese nivel
     *                          (usado para el scoping server-side de "Uso areas comunes").
     */
    public function obtenerTodosLosBloques(?int $idNivel = null)
    {
        try {
            $bloques = Bloque::with(['nivel', 'responsables:id_user,nombre,apellido,perfil'])
                ->when($idNivel !== null, fn ($q) => $q->where('id_nivel', $idNivel))
                ->get();

            return [
                'error' => false,
                'data' => $bloques,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function desactivarBloques(array $ids, int $estado)
    {
        try {
            Bloque::whereIn('id', $ids)->update(['activo' => $estado]);

            return [
                'error' => false,
                'message' => 'Bloques actualizados correctamente',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function asignarResponsables(int $idBloque, array $idUsuarios)
    {
        try {
            $bloque = Bloque::find($idBloque);

            if (!$bloque) {
                return [
                    'error' => true,
                    'message' => 'Bloque no encontrado',
                ];
            }

            $bloque->responsables()->sync($idUsuarios);

            return [
                'error' => false,
                'message' => 'Responsables actualizados correctamente',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function usuariosAsignablesBloque()
    {
        try {
            $usuarios = Usuario::where('estado', 'activo')
                ->whereIn('perfil', self::PERFILES_RESPONSABLES)
                ->select('id_user', DB::raw("CONCAT(nombre, ' ', apellido) AS nom_user"), 'perfil', 'id_nivel')
                ->orderBy('nombre')
                ->get();

            return [
                'error' => false,
                'data' => $usuarios,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}

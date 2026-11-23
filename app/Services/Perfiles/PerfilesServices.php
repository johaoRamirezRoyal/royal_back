<?php

namespace App\Services\Perfiles;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;

class PerfilesServices
{
    public function mostrarTodosPerfiles()
    {
        try {
            $perfiles = Perfil::whereNot('id_perfil', 1)->withCount('usuarios')->get();

            return [
                'error' => false,
                'data' => $perfiles,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => $e->getMessage(),
            ];
        }
    }

    /** Cambia el tipo (App\Enums\TipoPerfil) de un perfil. Super Admin no se toca. */
    public function actualizarTipo(int $idPerfil, TipoPerfil $tipo): array
    {
        $perfil = Perfil::find($idPerfil);

        if (! $perfil) {
            return ['error' => true, 'message' => 'El perfil no existe.', 'data' => null];
        }

        $perfil->update(['tipo' => $tipo->value]);

        return [
            'error' => false,
            'message' => "Tipo de \"{$perfil->nombre}\" actualizado.",
            'data' => ['id' => $perfil->id_perfil, 'tipo' => $perfil->tipo],
        ];
    }

    /** Crea un perfil nuevo. `estado` siempre "activo": se inactiva desde actualizarEstado(). */
    public function crear(array $datos): array
    {
        try {
            $perfil = Perfil::create([...$datos, 'estado' => 'activo']);

            return ['error' => false, 'message' => "Perfil \"{$perfil->nombre}\" creado.", 'data' => $perfil];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Edita nombre/tipo de un perfil. Super Admin no se toca (lo valida el controller). */
    public function actualizar(int $idPerfil, array $datos): array
    {
        $perfil = Perfil::find($idPerfil);

        if (! $perfil) {
            return ['error' => true, 'message' => 'El perfil no existe.'];
        }

        $perfil->update($datos);

        return ['error' => false, 'message' => "Perfil \"{$perfil->nombre}\" actualizado.", 'data' => $perfil];
    }

    /**
     * Activa o inactiva perfiles. Nunca se borran: `estado` solo oculta el perfil de los
     * selects de alta de usuario, no afecta a quienes ya lo tienen asignado.
     *
     * @param  array<int>  $ids
     */
    public function actualizarEstado(array $ids, string $estado): array
    {
        Perfil::whereIn('id_perfil', $ids)->update(['estado' => $estado]);

        return [
            'error' => false,
            'message' => $estado === 'activo' ? 'Perfil(es) activado(s).' : 'Perfil(es) inactivado(s).',
        ];
    }
}

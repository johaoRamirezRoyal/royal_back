<?php

namespace App\Services\Usuarios;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class UsuariosServices
{
    public function mostrarTodosUsuariosActivos()
    {
        return DB::select("SELECT id_user, documento, CONCAT(nombre, ' ', apellido) AS nom_user, 
                            correo, telefono, asignatura, user, perfil, estado, id_nivel, 
                            id_curso, id_grupo, fechareg
                            FROM usuarios WHERE estado = 'activo';");
    }

    public function mostrarTodosUsuariosActivoPaginado($perPage)
    {
        return DB::table('usuarios')
            ->select(
                'id_user',
                'documento',
                DB::raw("CONCAT(nombre, ' ', apellido) AS nom_user"),
                'correo',
                'telefono',
                'asignatura',
                'user',
                'perfil',
                'estado',
                'id_nivel',
                'id_curso',
                'id_grupo',
                'fechareg'
            )
            ->where('estado', 'activo')
            ->whereNotIn('perfil', [17, 16, 6])
            ->paginate($perPage);
    }

    public function tienePermiso($opcion, $perfil){
        try{
            $permiso = DB::table('cron_permisos as p')
            ->select('p.id', 'p.id_opcion')
            ->join('cron_opciones as o','o.id','=','p.id_opcion')
            ->join('cron_modulos as m', 'm.id', '=', 'o.id_modulo')
            ->where('p.id_opcion', $opcion)
            ->where('p.id_perfil', $perfil)
            ->where('p.activo', 1)
            ->exists();

            return ['permiso' => $permiso, 'error' => false];

        }catch(\Illuminate\Database\QueryException $e){
            return ['error' => true, 'message'=> $e->getMessage()];
        }
    }

    public function mostrarTodosUsuariosPaginado($perPage)
    {
        try{
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'perfil',
                'id_nivel',
                'id_grupo',
                'estado'
            ])
                ->with('perfilRelacion')
                ->whereNotIn('perfil', [17, 16, 6])
                ->paginate((int) $perPage);
            
            return [
                'error' => false,
                'data' => $usuarios,
            ];
        }catch(\Illuminate\Database\QueryException $e){
            return [
                'error' => true,
                'data' => $e->getMessage(),
            ];
        }
    }

    public function mostrarTodosUsuarios()
    {
        try{
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'perfil',
                'id_nivel',
                'id_grupo',
                'estado'
            ])
                ->with('perfilRelacion')
                ->whereNotIn('perfil', [17, 16, 6])
                ->get();
            
            return [
                'error' => false,
                'data' => $usuarios,
            ];
        }catch(\Exception $e){
            return [
                'error' => false,
                'data' => $e->getMessage(),
            ];
        }
    }

    public function mostrarInfoUsuarioId($id_usuario)
    {
        try {
            $usuario_info = Usuario::with('perfilRelacion')->find($id_usuario);

            if (!$usuario_info) {
                return [
                    'error' => true,
                    'usuario' => null
                ];
            }

            return [
                'error' => false,
                'usuario' => $usuario_info
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function actualizarUsuarios($id_usuario, array $data)
    {
        $usuario_info = Usuario::find($id_usuario);

        if (!$usuario_info) {
            return [
                'error' => true,
                'usuario' => null
            ];
        }

        try {
            // Solo actualizamos campos que están en $fillable
            $usuario_info->update($data);

            $usuario_return = Usuario::with('perfilRelacion')->find($id_usuario);

            return [
                'error' => false,
                'usuario' => $usuario_return
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}

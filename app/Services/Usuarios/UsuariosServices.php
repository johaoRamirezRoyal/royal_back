<?php 
namespace App\Services\Usuarios;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosServices 
{
    public function mostrarTodosUsuariosActivos(){
        return DB::select("SELECT id_user, documento, CONCAT(nombre, ' ', apellido) AS nom_user, 
                            correo, telefono, asignatura, user, perfil, estado, id_nivel, 
                            id_curso, id_grupo, fechareg
                            FROM usuarios WHERE estado = 'activo';");
    }

    public function mostrarTodosUsuariosActivoPaginado($perPage){
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

    public function crearNuevoUsuario(Usuario $usuario){
        $usuario = $usuario->toArray();

        DB::table('usuarios')->insert($usuario);
        return ['message' => 'Usuario creado'];
    }

}
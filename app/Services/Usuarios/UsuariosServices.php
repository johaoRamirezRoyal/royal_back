<?php

namespace App\Services\Usuarios;

use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\Usuarios\Firma;
use App\Models\Usuarios\Usuario;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsuariosServices
{
    public function __construct(
        private CloudinaryService $cloudinaryService
    ) {}

    public function userExistWhitEmail(string $email)
    {
        return Usuario::query()
            ->where('correo', $email)
            ->exists();
    }

    public function infoUserWhitEmail(string $email)
    {
        return Usuario::query()
            ->where('correo', $email)
            ->first();
    }

    public function mostrarTodosUsuariosActivos()
    {
        return DB::select("SELECT id_user, documento, CONCAT(nombre, ' ', apellido) AS nom_user, 
                            correo, telefono, asignatura, user, perfil, estado, id_nivel, 
                            id_curso, id_grupo, fechareg
                            FROM usuarios WHERE estado = 'activo';");
    }

    /**
     * Summary of mostrarTodosUsuariosActivoPaginado
     *
     * @param  mixed  $perPage
     * @return LengthAwarePaginator
     */
    public function mostrarTodosUsuariosActivoPaginado($perPage): array
    {
        try {
            $data = DB::table('usuarios')
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
                ->groupBy('nombre')
                ->whereNotIn('perfil', [17, 16, 6])
                ->paginate($perPage);

            return ['error' => false, 'message' => 'Datos obtenidos correctamente', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Summary of mostrarUsuariosPorPerfil
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarUsuariosPorPerfil(int $id_perfil)
    {
        try {
            $usuarios = Usuario::where('perfil', $id_perfil)
                ->where('estado', 'activo')
                ->get()
                ->makeHidden('foto_digital');
            if ($usuarios->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No hay usuarios en ese nivel especificado',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Usuarios obtenidos',
                'data' => $usuarios->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('No se obtubieron a los usuarios del perfil: '.$e->getMessage());

            return [
                'error' => true,
                'message' => 'Error obteniendo a los usuarios: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Actualiza la bandera `asistenciaRegistrada` de los usuarios indicados.
     * true: ya quedaron registrados en el dispositivo de asistencia.
     * false: se eliminaron del dispositivo (o nunca llegaron a registrarse).
     *
     * @param array<int> $idsUsuarios
     */
    public function actualizarAsistenciaRegistrada(array $idsUsuarios, bool $registrado): void
    {
        if (empty($idsUsuarios)) {
            return;
        }

        Usuario::whereIn('id_user', $idsUsuarios)->update(['asistenciaRegistrada' => $registrado]);
    }

    public function filtrarUsuarios($datos, $search, $sort, $dir, $perPage)
    {
        try {
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'perfil',
                'id_nivel',
                'id_grupo',
                'estado',
                'asignatura',
                'user',
                'user_log',
                'fechareg',
                'fecha_activo',
                'fecha_editado',
            ])->with([
                'perfilRelacion:id_perfil,nombre',
                'nivelRelacion:id,nombre',
            ])->when(trim((string) $search), function ($query, $search) {
                $palabras = preg_split('/\s+/', trim($search));

                $query->where(function ($q) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $q->where(function ($sub) use ($palabra) {
                            $sub->where('nombre', 'LIKE', "%$palabra%")
                                ->orWhere('apellido', 'LIKE', "%$palabra%")
                                ->orWhere('documento', 'LIKE', "%$palabra%")
                                ->orWhere('correo', 'LIKE', "%$palabra%")
                                ->orWhere('id_user', 'LIKE', "%$palabra%");
                        });
                    }
                });
            })->when($datos['perfiles'] ?? null, function ($query, $perfil) {
                $query->whereIn('perfil', $perfil);
            })
                ->when($datos['niveles'] ?? null, function ($query, $nivel) {
                    $query->whereIn('id_nivel', $nivel);
                })
                ->when($datos['id_grupo'] ?? null, function ($query, $grupo) {
                    $query->where('id_grupo', $grupo);
                })
                ->whereNotIn('perfil', [17, 16, 6]);

            if (! empty($sort)) {
                $usuarios
                    ->orderBy($sort, $dir);
            }

            $usuarios = $usuarios
                ->paginate((int) $perPage);

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

    public function tienePermiso($opcion, $perfil)
    {
        try {
            $permiso = DB::table('cron_permisos as p')
                ->select('p.id', 'p.id_opcion')
                ->join('cron_opciones as o', 'o.id', '=', 'p.id_opcion')
                ->join('cron_modulos as m', 'm.id', '=', 'o.id_modulo')
                ->where('p.id_opcion', $opcion)
                ->where('p.id_perfil', $perfil)
                ->where('p.activo', 1)
                ->exists();

            return ['permiso' => $permiso, 'error' => false];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Igual que tienePermiso() pero verifica varias opciones (OR) en una sola
     * consulta en vez de una por opción — usar cuando un endpoint acepta
     * cualquiera de varias opciones válidas (patrón sinAcceso() de controllers).
     */
    public function tieneAlgunPermiso(array $opciones, $perfil): array
    {
        try {
            $permiso = DB::table('cron_permisos as p')
                ->join('cron_opciones as o', 'o.id', '=', 'p.id_opcion')
                ->join('cron_modulos as m', 'm.id', '=', 'o.id_modulo')
                ->whereIn('p.id_opcion', $opciones)
                ->where('p.id_perfil', $perfil)
                ->where('p.activo', 1)
                ->exists();

            return ['permiso' => $permiso, 'error' => false];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function mostrarTodosUsuariosPaginado($perPage)
    {
        try {
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'user',
                'perfil',
                'id_nivel',
                'id_curso',
                'id_grupo',
                'estado',
            ])
                ->with([
                    'perfilRelacion:id_perfil,nombre',
                    'nivelRelacion:id,nombre',
                    'cursoRelacion:id,nombre',
                ])
                ->orderBy('nombre')
                ->orderBy('documento')
                ->whereNotIn('perfil', [17, 6])
                ->where('estado', 'activo')
                ->paginate((int) $perPage);

            return [
                'error' => false,
                'message' => 'Datos obtenidos satisfactoriamente',
                'data' => $usuarios,
            ];
        } catch (QueryException $e) {
            return [
                'error' => true,
                'message' => 'Ha ocurrido un error inesperado',
                'data' => $e->getMessage(),
            ];
        }
    }

    public function mostrarUsuariosPaginados(int $perPage, ?array $perfil_filtro, ?array $nivel_filtro, ?string $busqueda, ?string $estado = null, string $sort = 'nombre', string $dir = 'asc', ?array $curso_filtro = null)
    {
        $sortable = ['nombre', 'apellido', 'documento', 'correo', 'perfil', 'estado'];
        $sort = in_array($sort, $sortable, true) ? $sort : 'nombre';
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        try {
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'perfil',
                'user',
                'id_nivel',
                'id_curso',
                'id_grupo',
                'estado',
                'foto_carnet',
            ])
                ->with([
                    'perfilRelacion:id_perfil,nombre',
                    'nivelRelacion:id,nombre',
                    'cursoRelacion:id,nombre',
                ])
                ->when($perfil_filtro, function ($query, $perfiles) {
                    $query->whereIn('perfil', $perfiles);
                })
                ->when($nivel_filtro, function ($query, $niveles) {
                    $query->whereIn('id_nivel', $niveles);
                })
                ->when($curso_filtro, function ($query, $cursos) {
                    $query->whereIn('id_curso', $cursos);
                })
                ->when($busqueda, function ($query, $search) {
                    $palabras = preg_split('/\s+/', trim($search));
                    $query->where(function ($q) use ($palabras) {
                        foreach ($palabras as $palabra) {
                            $q->where(function ($sub) use ($palabra) {
                                $sub->where('nombre', 'LIKE', "%$palabra%")
                                    ->orWhere('apellido', 'LIKE', "%$palabra%")
                                    ->orWhere('documento', 'LIKE', "%$palabra%")
                                    ->orWhere('correo', 'LIKE', "%$palabra%");
                            });
                        }
                    });
                })
                ->when($estado, function ($query, $estado) {
                    $query->where('estado', $estado);
                })
                ->orderBy($sort, $dir)
                ->whereNotIn('perfil', [17, 6])
                ->paginate((int) $perPage);

            $this->adjuntarFotoYAcudientes($usuarios->getCollection());

            return [
                'error' => false,
                'message' => 'Datos obtenidos satisfactoriamente',
                'data' => $usuarios,
            ];
        } catch (QueryException $e) {
            return [
                'error' => true,
                'message' => 'Ha ocurrido un error inesperado',
                'data' => $e->getMessage(),
            ];
        }
    }

    /**
     * Agrega foto y acudientes a cada usuario con perfil estudiante (16) de la colección.
     * No hay columna de parentesco en estudiantes_padres, por lo que ese dato siempre viaja null.
     */
    private function adjuntarFotoYAcudientes($usuarios): void
    {
        $idsEstudiantes = $usuarios->where('perfil', 16)->pluck('id_user');

        $acudientesPorEstudiante = EstudiantesPadre::whereIn('id_estudiante', $idsEstudiantes)
            ->where('activo', 1)
            ->with('acudiente:id_user,nombre,apellido,telefono,correo')
            ->get()
            ->filter(fn ($padre) => $padre->acudiente)
            ->groupBy('id_estudiante');

        foreach ($usuarios as $usuario) {
            $usuario->foto = $usuario->foto_carnet;

            if ($usuario->perfil != 16) {
                $usuario->acudientes = null;
                continue;
            }

            $acudientes = $acudientesPorEstudiante->get($usuario->id_user, collect());

            $usuario->acudientes = $acudientes->isEmpty() ? null : $acudientes->map(fn ($padre) => [
                'nombre' => $padre->acudiente->nombre,
                'apellido' => $padre->acudiente->apellido,
                'parentesco' => null,
                'telefono' => $padre->celular ?: $padre->acudiente->telefono,
                'correo' => $padre->acudiente->correo,
            ])->values()->all();
        }
    }

    public function mostrarTodosUsuarios()
    {
        try {
            $usuarios = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'user',
                'perfil',
                'id_nivel',
                'id_grupo',
                'estado',
            ])
                ->with('perfilRelacion')
                ->whereNotIn('perfil', [17, 16, 6])
                ->get();

            return [
                'error' => false,
                'data' => $usuarios,
            ];
        } catch (\Exception $e) {
            return [
                'error' => false,
                'data' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listado liviano de usuarios activos para poblar selects (ej. inventario): incluye
     * trabajadores y estudiantes, excluye solo Proveedor (17) y Acudiente (6).
     */
    public function usuariosSelectInventario()
    {
        try {
            $usuarios = Usuario::where('estado', 'activo')
                ->whereNotIn('perfil', [17, 6])
                ->select(
                    'id_user',
                    DB::raw("CONCAT(nombre, ' ', apellido) AS nom_user"),
                    'nombre',
                    'apellido',
                    'documento',
                    'perfil'
                )
                ->orderBy('nombre')
                ->get();

            return [
                'error' => false,
                'data' => $usuarios,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Error obteniendo usuarios: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function mostrarInfoUsuarioId($id_usuario)
    {
        try {
            $usuario_info = Usuario::with('perfilRelacion')->find($id_usuario);

            if (! $usuario_info) {
                return [
                    'error' => true,
                    'usuario' => null,
                ];
            }

            return [
                'error' => false,
                'usuario' => $usuario_info,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function agregarUsuario(array $data)
    {
        try {
            $usuario = Usuario::create($data);

            return [
                'error' => false,
                'usuario' => $usuario,
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

        if (! $usuario_info) {
            return [
                'error' => true,
                'usuario' => null,
            ];
        }

        try {
            // Solo actualizamos campos que están en $fillable
            $usuario_info->update($data);

            $usuario_return = Usuario::with('perfilRelacion')->find($id_usuario);

            return [
                'error' => false,
                'usuario' => $usuario_return,
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function actualizarEstadoUsuarios(array $id_usuarios, string $estado)
    {
        try {
            Usuario::whereIn('id_user', $id_usuarios)->update(['estado' => $estado]);

            return [
                'error' => false,
                'message' => 'Usuarios actualizados correctamente',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function mostrarUsuariosConInscripciones($perPage)
    {
        try {
            $data = Usuario::with([
                'inscripciones.estadoInscripcion:id,nombre',
                'inscripciones.anioAcademico',
                'inscripciones.aspirante',
            ])
                ->has('inscripciones')
                ->paginate($perPage);

            return [
                'error' => false,
                'message' => 'Se han obtenido correctamente los datos solicitados',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function verFirmaUsuario(int $id_user): array
    {
        try {
            $firma = Firma::where('id_user', $id_user)->where('activo', 1)->first();

            return [
                'error' => false,
                'firma_url' => $firma->url ?? null,
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function subirFirmaUsuario(int $id_user, UploadedFile $file): array
    {
        try {
            $resultado = $this->cloudinaryService->uploadFile($file, 'usuarios/firmas');

            if ($resultado['error']) {
                return ['error' => true, 'message' => $resultado['message']];
            }

            Firma::updateOrCreate(
                ['id_user' => $id_user],
                [
                    'nombre' => $file->getClientOriginalName(),
                    'url' => $resultado['data']['url'],
                    'activo' => 1,
                    'user_log' => $id_user,
                ]
            );

            Usuario::where('id_user', $id_user)->update(['firma_digital' => 1]);

            return [
                'error' => false,
                'firma_url' => $resultado['data']['url'],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

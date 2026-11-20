<?php

namespace App\Services\Usuarios;

use App\Enums\TipoPerfil;
use App\Http\Requests\Usuarios\RegistrarUsuarioRequest;
use App\Mail\NuevoUsuarioMail;
use App\Models\Areas\Cursos;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Models\PerfilUsuario\InfoAdicionalUsuario;
use App\Models\Usuarios\Firma;
use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\MailService;
use App\Support\Permiso;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UsuariosServices
{
    public function __construct(
        private CloudinaryService $cloudinaryService,
        private MailService $mailService
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
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ESTUDIANTE, TipoPerfil::ACUDIENTE))
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
     * @param  array<int>  $idsUsuarios
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
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ESTUDIANTE, TipoPerfil::ACUDIENTE));

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

    public function mostrarTodosUsuariosPaginado($perPage, ?int $primeroId = null)
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
                'id_nivel_2',
                'id_curso',
                'id_grupo',
                'estado',
            ])
                ->with([
                    'perfilRelacion:id_perfil,nombre,tipo',
                    'nivelRelacion:id,nombre',
                    'nivel2Relacion:id,nombre',
                    'cursoRelacion:id,nombre',
                ])
                // El usuario autenticado va en la primera posición del listado de /users.
                ->when($primeroId, fn ($q, $id) => $q->orderByRaw('id_user = ? DESC', [$id]))
                ->orderBy('nombre')
                ->orderBy('documento')
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ACUDIENTE))
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

    public function mostrarUsuariosPaginados(int $perPage, ?array $perfil_filtro, ?array $nivel_filtro, ?string $busqueda, ?string $estado = null, string $sort = 'nombre', string $dir = 'asc', ?array $curso_filtro = null, ?int $primeroId = null)
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
                'id_nivel_2',
                'id_curso',
                'id_grupo',
                'estado',
                'foto_carnet',
            ])
                ->with([
                    'perfilRelacion:id_perfil,nombre,tipo',
                    'nivelRelacion:id,nombre',
                    'nivel2Relacion:id,nombre',
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
                // El usuario autenticado va en la primera posición del listado de /users.
                ->when($primeroId, fn ($q, $id) => $q->orderByRaw('id_user = ? DESC', [$id]))
                ->orderBy($sort, $dir)
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ACUDIENTE))
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
     * Listado paginado de acudientes (tipo ACUDIENTE: los que crea el colegio y los que se
     * auto-registran en Admisiones) para el módulo Acudientes (AcudientesController) y la
     * vinculación de hijos. A diferencia de mostrarUsuariosPaginados (que EXCLUYE
     * perfil 6 a propósito, ver whereNotIn más arriba), acá se filtra directo a ese
     * perfil — no se puede simplemente quitar la exclusión de aquel método porque el
     * resto de sus consumidores (el módulo /users) sigue necesitando que se excluya.
     */
    public function mostrarAcudientesPaginados(int $perPage, ?string $busqueda, ?string $estado, string $sort = 'nombre', string $dir = 'asc', bool $soloAutoregistrados = false, ?string $acceso = null)
    {
        $sortable = ['nombre', 'apellido', 'documento', 'correo', 'estado', 'fechareg', 'ultima_conexion', 'acceso_portal_enviado_at'];
        $sort = in_array($sort, $sortable, true) ? $sort : 'nombre';
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        try {
            $acudientes = Usuario::select([
                'id_user',
                'documento',
                'nombre',
                'apellido',
                'correo',
                'telefono',
                'estado',
                'fechareg',
                'ultima_ip',
                'ultima_conexion',
                'fecha_editado',
                'origen_registro',
                // Cuándo se le envió el correo de acceso al portal (null = nunca).
                'acceso_portal_enviado_at',
            ])
                ->withCount('inscripciones')
                ->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ACUDIENTE))
                // Marca explícita que AdmissionsController::familyRegister setea al crear
                // la cuenta — reemplaza la heurística anterior basada en user_log (quién
                // creó el registro), que no era confiable: hay usuarios sin el id del
                // admin aunque tampoco vinieron de un auto-registro.
                ->when($soloAutoregistrados, function ($query) {
                    $query->where('origen_registro', 'admisiones');
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
                ->when($acceso === 'enviado', fn ($query) => $query->whereNotNull('acceso_portal_enviado_at'))
                ->when($acceso === 'sin_enviar', fn ($query) => $query->whereNull('acceso_portal_enviado_at'))
                // Hijos vinculados (activos): el módulo Acudientes los muestra en la lista.
                ->addSelect(['hijos_count' => EstudiantesPadre::selectRaw('COUNT(*)')->whereColumn('id_acudiente', 'usuarios.id_user')->where('activo', 1)])
                ->orderBy($sort, $dir)
                ->paginate($perPage);

            return [
                'error' => false,
                'message' => 'Datos obtenidos satisfactoriamente',
                'data' => $acudientes,
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
        $perfilesEstudiante = Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE);
        $idsEstudiantes = $usuarios->whereIn('perfil', $perfilesEstudiante)->pluck('id_user');

        $acudientesPorEstudiante = EstudiantesPadre::whereIn('id_estudiante', $idsEstudiantes)
            ->where('activo', 1)
            ->with('acudiente:id_user,nombre,apellido,telefono,correo')
            ->get()
            ->filter(fn ($padre) => $padre->acudiente)
            ->groupBy('id_estudiante');

        foreach ($usuarios as $usuario) {
            $usuario->foto = $usuario->foto_carnet;

            if (! in_array((int) $usuario->perfil, $perfilesEstudiante, true)) {
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
                'id_nivel_2',
                'id_grupo',
                'estado',
            ])
                ->with(['perfilRelacion', 'nivelRelacion:id,nombre', 'nivel2Relacion:id,nombre'])
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ESTUDIANTE, TipoPerfil::ACUDIENTE))
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
                ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR, TipoPerfil::ACUDIENTE))
                ->select(
                    'id_user',
                    DB::raw("CONCAT(nombre, ' ', apellido) AS nom_user"),
                    'nombre',
                    'apellido',
                    'documento',
                    'perfil',
                    // Áreas Comunes: para preseleccionar como responsable a la
                    // asistencia de nivel (opción 165) del mismo nivel del bloque.
                    'id_nivel'
                )
                ->orderBy('nombre')
                ->get();

            $asistentes = PermisosPerfil::perfilesCon(Permiso::ASISTENCIA_NIVEL);
            $usuarios->each(fn ($u) => $u->asistente_nivel = in_array((int) $u->perfil, $asistentes, true));

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
            $passPlano = $data['pass'] ?? null;
            $genero = $data['genero'] ?? null;
            unset($data['genero']);
            $usuario = Usuario::create($data);

            if ($genero) {
                InfoAdicionalUsuario::create(['id_user' => $usuario->id_user, 'genero' => $genero]);
            }

            if ($passPlano) {
                $this->mailService->send($usuario->correo, new NuevoUsuarioMail($usuario, $passPlano));
            }

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

    /**
     * Igual que actualizarEstadoUsuarios pero acotado a perfiles de tipo Acudiente — así
     * este endpoint (pensado solo para el módulo de gestión de acudientes) no se puede
     * usar para tocar el estado de usuarios de otro perfil aunque lleguen IDs ajenos.
     */
    public function actualizarEstadoAcudientes(array $id_usuarios, string $estado)
    {
        try {
            Usuario::whereIn('id_user', $id_usuarios)
                ->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ACUDIENTE))
                // fecha_editado no se escribía en ningún lado del código hasta ahora —
                // acá queda como "última vez que un staff cambió algo de esta cuenta
                // desde este panel" (hoy la única acción de edición que existe es esta).
                ->update(['estado' => $estado, 'fecha_editado' => now()]);

            return [
                'error' => false,
                'message' => 'Acudientes actualizados correctamente',
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
            $firma = Firma::where('id_user', $id_user)->where('activo', 1)->whereNotNull('url')->first();

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
            // public_id único: con el nombre original (overwrite) dos usuarios que subían
            // "firma.png" se pisaban el archivo y uno quedaba firmando con la firma del otro.
            $resultado = $this->cloudinaryService->uploadFile(
                $file,
                CloudinaryService::carpetaColegio('usuarios/firmas'),
                "firma_{$id_user}_".Str::lower(Str::random(8))
            );

            if ($resultado['error']) {
                return ['error' => true, 'message' => $resultado['message']];
            }

            $nueva = [
                'id_user' => $id_user,
                'nombre' => $file->getClientOriginalName(),
                'url' => $resultado['data']['url'],
                'activo' => 1,
                'user_log' => $id_user,
            ];

            // Solo la activa: las inactivas son firmas subidas a mano para una historia clínica
            // (AdmisionesServices::actualizarEstadoCitaPsicologia) o firmas reemplazadas que
            // siguen firmando una historia, y no deben pisarse. Y solo con url: las activas sin url
            // son del sistema anterior, que sigue leyendo la tabla, y tampoco se pisan.
            $anterior = Firma::where('id_user', $id_user)->where('activo', 1)->whereNotNull('url')->first();
            $urlABorrar = null;

            if (! $anterior) {
                Firma::create($nueva);
            } elseif (HceObservacionesFirmas::where('id_firma_psicologa', $anterior->id)->exists()) {
                // Una historia clínica ya firmada apunta a esta fila (por id): se conserva, con su
                // archivo, para que no cambie de firma ni la pierda.
                $anterior->update(['activo' => 0]);
                Firma::create($nueva);
            } else {
                $urlABorrar = $anterior->url;
                $anterior->update($nueva);
            }

            Usuario::where('id_user', $id_user)->update(['firma_digital' => 1]);

            // Después de guardar la nueva: si el borrado falla, solo queda un archivo huérfano.
            if ($urlABorrar) {
                $borrado = $this->cloudinaryService->deleteFileByUrl($urlABorrar);
                if ($borrado['error']) {
                    Log::warning("No se pudo borrar de Cloudinary la firma reemplazada del usuario {$id_user}: {$borrado['message']}");
                }
            }

            return [
                'error' => false,
                'firma_url' => $resultado['data']['url'],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ponytail: correo de bienvenida va síncrono por fila (igual que agregarUsuario en el
    // alta individual) — con archivos grandes esto es lento; si se vuelve un problema,
    // encolar NuevoUsuarioMail. Mientras tanto el límite de filas de abajo lo acota.
    private const MAX_FILAS_IMPORTACION_USUARIOS = 500;

    /**
     * Creación/actualización masiva desde Excel: columna A documento, B nombre,
     * C apellido, D correo, E teléfono (opcional), F nivel (nombre, ver `nivel.nombre`),
     * G curso (nombre, opcional); fila 1 = encabezados. El perfil es fijo para todo el
     * archivo (se elige antes de subir, no es columna). Documento ya existente => actualiza
     * en vez de crear (sin tocar la contraseña ni el username). Contraseña y usuario de un
     * usuario nuevo = su documento. Cada fila se reporta por separado; las válidas se
     * aplican aunque otras fallen.
     */
    public function importarExcel(string $ruta, int $perfil, int $userLog): array
    {
        try {
            $hoja = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'No se pudo leer el archivo. Verifica que sea un Excel válido.', 'data' => []];
        }

        $filas = [];
        foreach (array_slice($hoja, 1, null, true) as $i => $celdas) {
            if (! array_filter($celdas, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $filas[] = [
                'fila' => $i + 1,
                'documento' => $this->normalizarDocumentoImportacion($celdas[0] ?? null),
                'nombre' => trim((string) ($celdas[1] ?? '')),
                'apellido' => trim((string) ($celdas[2] ?? '')) ?: null,
                'correo' => strtolower(trim((string) ($celdas[3] ?? ''))),
                'telefono' => trim((string) ($celdas[4] ?? '')) ?: null,
                'nivel' => trim((string) ($celdas[5] ?? '')),
                'curso' => trim((string) ($celdas[6] ?? '')) ?: null,
            ];
        }

        if (count($filas) === 0) {
            return ['error' => true, 'message' => 'El archivo no tiene filas para importar.', 'data' => []];
        }

        if (count($filas) > self::MAX_FILAS_IMPORTACION_USUARIOS) {
            return ['error' => true, 'message' => 'El archivo supera el máximo de '.self::MAX_FILAS_IMPORTACION_USUARIOS.' filas.', 'data' => []];
        }

        $nivelesPorNombre = Nivel::pluck('id', 'nombre')
            ->mapWithKeys(fn ($id, $nombre) => [mb_strtolower(trim((string) $nombre)) => $id]);
        $cursosPorNombre = Cursos::pluck('id', 'nombre')
            ->mapWithKeys(fn ($id, $nombre) => [mb_strtolower(trim((string) $nombre)) => $id]);
        $usuariosExistentes = Usuario::whereIn('documento', array_column($filas, 'documento'))
            ->get(['id_user', 'documento'])
            ->keyBy('documento');

        $resumen = ['creados' => 0, 'actualizados' => 0, 'errores' => []];

        foreach ($filas as $fila) {
            $error = function (string $mensaje) use ($fila, &$resumen) {
                $resumen['errores'][] = [
                    'fila' => $fila['fila'],
                    'documento' => $fila['documento'],
                    'nombre' => $fila['nombre'],
                    'mensaje' => $mensaje,
                ];
            };

            $idNivel = null;
            if ($fila['nivel'] !== '') {
                $idNivel = $nivelesPorNombre->get(mb_strtolower($fila['nivel']));
                if ($idNivel === null) {
                    $error("El nivel \"{$fila['nivel']}\" no existe.");

                    continue;
                }
            }

            $idCurso = null;
            if ($fila['curso']) {
                $idCurso = $cursosPorNombre->get(mb_strtolower($fila['curso']));
                if ($idCurso === null) {
                    $error("El curso \"{$fila['curso']}\" no existe.");

                    continue;
                }
            }

            $existente = $usuariosExistentes->get($fila['documento']);

            $dataBase = [
                'documento' => $fila['documento'],
                'nombre' => $fila['nombre'],
                'apellido' => $fila['apellido'],
                'correo' => $fila['correo'],
                'perfil' => $perfil,
                'id_nivel' => $idNivel,
                'id_curso' => $idCurso,
                'telefono' => $fila['telefono'],
            ];

            if ($existente) {
                $reglas = (new RegistrarUsuarioRequest)->rules();
                unset($reglas['pass'], $reglas['user']);
                $reglas['documento'] = ['required', 'numeric', 'digits_between:5,16'];
                $reglas['correo'] = [
                    'required',
                    'email',
                    'ends_with:@royalschool.edu.co',
                    Rule::unique('usuarios', 'correo')->ignore($existente->id_user, 'id_user'),
                ];

                $validator = Validator::make($dataBase, $reglas, (new RegistrarUsuarioRequest)->messages());
                if ($validator->fails()) {
                    $error($validator->errors()->first());

                    continue;
                }

                $dataBase['fecha_editado'] = now();
                $respuesta = $this->actualizarUsuarios($existente->id_user, $dataBase);
                if ($respuesta['error']) {
                    $error($respuesta['message'] ?? 'No se pudo actualizar.');

                    continue;
                }

                $resumen['actualizados']++;
            } else {
                $data = $dataBase + ['user' => $fila['documento']];

                $validator = Validator::make($data, (new RegistrarUsuarioRequest)->rules(), (new RegistrarUsuarioRequest)->messages());
                if ($validator->fails()) {
                    $error($validator->errors()->first());

                    continue;
                }

                $data['pass'] = $fila['documento'];
                $data['fechareg'] = now();
                $data['user_log'] = $userLog;

                $respuesta = $this->agregarUsuario($data);
                if ($respuesta['error']) {
                    $error($respuesta['message'] ?? 'No se pudo crear.');

                    continue;
                }

                $resumen['creados']++;
            }
        }

        return [
            'error' => false,
            'message' => "Importación finalizada: {$resumen['creados']} creado(s), {$resumen['actualizados']} actualizado(s), ".count($resumen['errores']).' con error.',
            'data' => $resumen,
        ];
    }

    private function normalizarDocumentoImportacion(mixed $valor): string
    {
        // Excel entrega documentos numéricos como float (1048082892.0).
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = number_format($valor, 0, '', '');
        }

        return preg_replace('/[^0-9A-Za-z]/', '', (string) $valor);
    }
}

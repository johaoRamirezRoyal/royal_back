<?php

namespace App\Services\Enfermeria;

use App\Models\Enfermeria\EnfermeriaAtencion;
use App\Models\Enfermeria\EnfermeriaCategoria;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Services\MailService;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class EnfermeriaServices extends Service
{
    public function __construct(
        private MailService $mailService
    ) {}

    /*
    -------------------------------------------------
    |
    |             CATEGORÍAS
    |
    -------------------------------------------------
    */

    /**
     * Obtener todas las categorías de enfermería
     */
    public function obtenerCategorias(?string $search = null, ?int $perPage = 15): array
    {
        try {
            $categorias = EnfermeriaCategoria::query()
                ->when($search, fn($q) => $q->where('nombre', 'like', "%{$search}%"))
                ->orderByDesc('id')
                ->paginate($perPage);

            return [
                'error' => false,
                'message' => 'Categorías obtenidas correctamente',
                'data' => $categorias,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener las categorías de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las categorías',
                'data' => [],
            ];
        }
    }

    /**
     * Obtener todas las categorías activas (para selects)
     */
    public function obtenerCategoriasActivas(?int $perPage = null): array
    {
        try {
            $query = EnfermeriaCategoria::where('activo', 1)
                ->orderBy('nombre');

            $categorias = $perPage
                ? $query->paginate($perPage)
                : $query->paginate(15);

            return [
                'error' => false,
                'message' => 'Categorías activas obtenidas',
                'data' => $categorias,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener categorías activas de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener categorías activas',
                'data' => [],
            ];
        }
    }

    /**
     * Obtener una categoría por ID
     */
    public function obtenerCategoriaPorId(int $id): array
    {
        try {
            $categoria = EnfermeriaCategoria::find($id);

            if (!$categoria) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la categoría con ID: ' . $id,
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Categoría obtenida',
                'data' => $categoria,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener la categoría de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la categoría',
                'data' => [],
            ];
        }
    }

    /**
     * Crear una nueva categoría de enfermería
     */
    public function crearCategoria(array $datos): array
    {
        try {
            if (empty($datos)) {
                return [
                    'error' => true,
                    'message' => 'No llegaron datos al servidor',
                    'data' => [],
                ];
            }

            $categoria = EnfermeriaCategoria::create($datos);

            return [
                'error' => false,
                'message' => 'Categoría creada correctamente',
                'data' => $categoria->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al crear la categoría de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la categoría',
                'data' => [],
            ];
        }
    }

    /**
     * Actualizar una categoría de enfermería
     */
    public function actualizarCategoria(array $datos, int $id): array
    {
        try {
            $categoria = EnfermeriaCategoria::find($id);

            if (!$categoria) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la categoría con ID: ' . $id,
                    'data' => [],
                ];
            }

            $categoria->update($datos);

            return [
                'error' => false,
                'message' => 'Categoría actualizada correctamente',
                'data' => $categoria->refresh()->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al actualizar la categoría de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la categoría',
                'data' => [],
            ];
        }
    }

    /**
     * Cambiar el estado (activo/inactivo) de una categoría
     */
    public function cambiarEstadoCategoria(int $id, int $estado): array
    {
        try {
            $categoria = EnfermeriaCategoria::find($id);

            if (!$categoria) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la categoría con ID: ' . $id,
                    'data' => [],
                ];
            }

            if (!in_array($estado, [0, 1])) {
                return [
                    'error' => true,
                    'message' => 'Estado inválido. Use 0 para inactivo o 1 para activo',
                    'data' => $categoria->toArray(),
                ];
            }

            $categoria->update(['activo' => $estado]);

            return [
                'error' => false,
                'message' => 'Estado de la categoría actualizado correctamente',
                'data' => $categoria->refresh()->toArray(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado de la categoría de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al cambiar el estado de la categoría',
                'data' => [],
            ];
        }
    }

    /**
     * Eliminar una categoría de enfermería
     */
    public function eliminarCategoria(int $id): array
    {
        try {
            $categoria = EnfermeriaCategoria::find($id);

            if (!$categoria) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la categoría con ID: ' . $id,
                    'data' => [],
                ];
            }

            $categoria->delete();

            return [
                'error' => false,
                'message' => 'Categoría eliminada correctamente',
                'data' => [],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al eliminar la categoría de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la categoría',
                'data' => [],
            ];
        }
    }

    /**
     * Select de categorías activas: devuelve [{value, label}] listo para dropdowns
     */
    public function selectCategorias(): array
    {
        try {
            $categorias = EnfermeriaCategoria::where('activo', 1)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tratamiento_sugerido']);

            $select = $categorias->map(fn($c) => [
                'value' => $c->id,
                'label' => $c->nombre,
                'tratamiento_sugerido' => $c->tratamiento_sugerido,
            ])->values();

            return [
                'error' => false,
                'message' => 'Select de categorías obtenido',
                'data' => $select,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener select de categorías');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el select de categorías',
                'data' => [],
            ];
        }
    }

    /**
     * Estadísticas del módulo enfermería: total, activas, inactivas
     */
    public function estadisticas(): array
    {
        try {
            $total = EnfermeriaCategoria::count();
            $activas = EnfermeriaCategoria::where('activo', 1)->count();
            $inactivas = $total - $activas;

            return [
                'error' => false,
                'message' => 'Estadísticas obtenidas',
                'data' => [
                    'total' => $total,
                    'activas' => $activas,
                    'inactivas' => $inactivas,
                ],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener estadísticas de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas',
                'data' => [],
            ];
        }
    }

    /**
     * Verificar si ya existe una categoría con ese nombre (evita duplicados)
     */
    public function nombreExiste(string $nombre, ?int $ignorarId = null): array
    {
        try {
            $query = EnfermeriaCategoria::where('nombre', $nombre);

            if ($ignorarId) {
                $query->where('id', '!=', $ignorarId);
            }

            $existe = $query->exists();

            return [
                'error' => false,
                'message' => $existe ? 'El nombre ya está registrado' : 'El nombre está disponible',
                'data' => ['existe' => $existe],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al verificar el nombre de la categoría');
            return [
                'error' => true,
                'message' => 'Error en el servidor al verificar el nombre',
                'data' => [],
            ];
        }
    }

    /**
     * Cambiar estado de múltiples categorías de una vez
     */
    public function cambiarEstadoMasivo(array $ids, int $estado): array
    {
        try {
            if (!in_array($estado, [0, 1])) {
                return [
                    'error' => true,
                    'message' => 'Estado inválido. Use 0 para inactivo o 1 para activo',
                    'data' => [],
                ];
            }

            $actualizados = EnfermeriaCategoria::whereIn('id', $ids)->update(['activo' => $estado]);

            if ($actualizados < 1) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron categorías para actualizar',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => "{$actualizados} categoría(s) actualizada(s) correctamente",
                'data' => ['actualizados' => $actualizados],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al cambiar estado masivo de categorías');
            return [
                'error' => true,
                'message' => 'Error en el servidor al cambiar el estado masivo',
                'data' => [],
            ];
        }
    }

    /**
     * Obtener resumen: categorías con conteo de registros relacionados (para tablas admin)
     */
    public function obtenerCategoriasConConteo(): array
    {
        try {
            $categorias = EnfermeriaCategoria::select(
                'id',
                'nombre',
                'tratamiento_sugerido',
                'activo',
                'fechareg'
            )
                ->orderByDesc('id')
                ->get();

            return [
                'error' => false,
                'message' => 'Categorías con conteo obtenidas',
                'data' => $categorias,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener categorías con conteo');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el resumen de categorías',
                'data' => [],
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             ATENCIONES
    |
    -------------------------------------------------
    */

    /**
     * Registrar una nueva atención de enfermería.
     * Si envio=1, envía correo al acudiente del estudiante.
     * Si no se envía tratamiento, usa el tratamiento_sugerido de la categoría.
     */
    public function crearAtencion(array $datos): array
    {
        try {
            return DB::transaction(function () use ($datos) {

                $categoria = EnfermeriaCategoria::find($datos['motivo']);

                if (!$categoria) {
                    return [
                        'error' => true,
                        'message' => 'La categoría seleccionada no existe',
                        'data' => [],
                    ];
                }

                if (empty($datos['tratamiento']) && $categoria->tratamiento_sugerido) {
                    $datos['tratamiento'] = $categoria->tratamiento_sugerido;
                }

                $datos['envio'] = $datos['envio'] ?? 0;
                $datos['enviado'] = 0;
                $datos['fechareg'] = now();

                $atencion = EnfermeriaAtencion::create($datos);

                if ($atencion->envio == 1) {
                    $this->enviarCorreoAcudiente($atencion);
                }

                return [
                    'error' => false,
                    'message' => 'Atención registrada correctamente',
                    'data' => $atencion->load([
                        'estudiante:id_user,nombre,apellido,documento',
                        'categoria:id,nombre,tratamiento_sugerido',
                        'registradoPor:id_user,nombre,apellido',
                    ])->toArray(),
                ];
            });
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al registrar la atención de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar la atención',
                'data' => [],
            ];
        }
    }

    /**
     * Enviar correo al acudiente del estudiante.
     */
    private function enviarCorreoAcudiente(EnfermeriaAtencion $atencion): void
    {
        try {
            $acudientes = EstudiantesPadre::where('id_estudiante', $atencion->id_user)
                ->where('activo', 1)
                ->pluck('id_acudiente');

            if ($acudientes->isEmpty()) {
                $atencion->update(['enviado' => 0]);
                return;
            }

            $correos = \App\Models\Usuarios\Usuario::whereIn('id_user', $acudientes)
                ->whereNotNull('correo')
                ->pluck('correo')
                ->filter()
                ->values()
                ->toArray();

            if (empty($correos)) {
                $atencion->update(['enviado' => 0]);
                return;
            }

            $estudiante = $atencion->estudiante;
            $categoria = $atencion->categoria;

            $titulo = "Notificación Enfermería | Atención registrada";
            $contenido = "Se ha registrado una atención en enfermería para el estudiante:\n\n";
            $contenido .= "Estudiante: {$estudiante->nombre} {$estudiante->apellido}\n";
            $contenido .= "Documento: {$estudiante->documento}\n";
            $contenido .= "Motivo: {$categoria->nombre}\n";
            $contenido .= "Tratamiento: {$atencion->tratamiento}\n";
            $contenido .= "Fecha: " . ($atencion->fechareg ? $atencion->fechareg->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A')) . "\n";

            $resultado = $this->mailService->sendGeneric($correos, $titulo, $contenido);

            $atencion->update(['enviado' => $resultado['error'] ? 0 : 1]);
        } catch (\Exception $e) {
            $atencion->update(['enviado' => 0]);
        }
    }

    /**
     * Reenviar correo de una atención ya registrada.
     */
    public function reenviarCorreo(int $idAtencion): array
    {
        try {
            $atencion = EnfermeriaAtencion::find($idAtencion);

            if (!$atencion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la atención con ID: ' . $idAtencion,
                    'data' => [],
                ];
            }

            $this->enviarCorreoAcudiente($atencion);

            $atencion->refresh();

            return [
                'error' => false,
                'message' => $atencion->enviado
                    ? 'Correo enviado correctamente'
                    : 'No se pudo enviar el correo. Verifique que el estudiante tenga acudientes con correo registrado.',
                'data' => ['enviado' => $atencion->enviado],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al reenviar correo de atención');
            return [
                'error' => true,
                'message' => 'Error en el servidor al reenviar el correo',
                'data' => [],
            ];
        }
    }

    /**
     * Listado paginado de atenciones con filtros.
     */
    public function obtenerAtenciones(
        ?string $search = null,
        ?int $idEstudiante = null,
        ?int $idCategoria = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?int $perPage = 15,
        ?string $sort = null,
        string $dir = 'desc'
    ): array {
        try {
            $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

            $atenciones = EnfermeriaAtencion::query()
                ->with([
                    'estudiante:id_user,nombre,apellido,documento,correo',
                    'categoria:id,nombre',
                    'registradoPor:id_user,nombre,apellido',
                ])
                ->when($search, fn($q) => $q->whereHas('estudiante', fn($e) => $e
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                    ->orWhere('documento', 'like', "%{$search}%")
                    ->orWhere('id_user', 'like', "%{$search}%")
                ))
                ->when($idEstudiante, fn($q) => $q->where('id_user', $idEstudiante))
                ->when($idCategoria, fn($q) => $q->where('motivo', $idCategoria))
                ->when($fechaDesde, fn($q) => $q->whereDate('fechareg', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('fechareg', '<=', $fechaHasta))
                ->when($sort === 'fechareg', fn($q) => $q->orderBy('fechareg', $dir))
                ->when($sort !== 'fechareg', fn($q) => $q->orderByDesc('id'))
                ->paginate($perPage);

            return [
                'error' => false,
                'message' => 'Atenciones obtenidas correctamente',
                'data' => $atenciones,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener las atenciones de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las atenciones',
                'data' => [],
            ];
        }
    }

    /**
     * Obtener una atención por ID.
     */
    public function obtenerAtencionPorId(int $id): array
    {
        try {
            $atencion = EnfermeriaAtencion::with([
                'estudiante:id_user,nombre,apellido,documento,correo',
                'categoria:id,nombre,tratamiento_sugerido',
                'registradoPor:id_user,nombre,apellido',
            ])->find($id);

            if (!$atencion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la atención con ID: ' . $id,
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Atención obtenida',
                'data' => $atencion,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener la atención de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la atención',
                'data' => [],
            ];
        }
    }

    /**
     * Eliminar una atención.
     */
    public function eliminarAtencion(int $id): array
    {
        try {
            $atencion = EnfermeriaAtencion::find($id);

            if (!$atencion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la atención con ID: ' . $id,
                    'data' => [],
                ];
            }

            $atencion->delete();

            return [
                'error' => false,
                'message' => 'Atención eliminada correctamente',
                'data' => [],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al eliminar la atención de enfermería');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la atención',
                'data' => [],
            ];
        }
    }

    /**
     * Estadísticas de atenciones: total, enviados, no enviados.
     */
    public function estadisticasAtenciones(): array
    {
        try {
            $total = EnfermeriaAtencion::count();
            $enviados = EnfermeriaAtencion::where('enviado', 1)->count();
            $noEnviados = $total - $enviados;

            return [
                'error' => false,
                'message' => 'Estadísticas de atenciones obtenidas',
                'data' => [
                    'total' => $total,
                    'enviados' => $enviados,
                    'no_enviados' => $noEnviados,
                ],
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener estadísticas de atenciones');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas de atenciones',
                'data' => [],
            ];
        }
    }

    /**
     * Cursos con más atenciones médicas.
     */
    public function cursosConMasAtenciones(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $query = EnfermeriaAtencion::select(
                'id_user',
                DB::raw('COUNT(*) as total_atenciones')
            )
                ->when($fechaDesde, fn($q) => $q->whereDate('fechareg', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('fechareg', '<=', $fechaHasta))
                ->groupBy('id_user');

            $estudiantes = (clone $query)->pluck('id_user');

            $cursos = \App\Models\Areas\Cursos::select('id', 'nombre')
                ->whereIn('id', fn($q) => $q->select('id_curso')
                    ->from('usuarios')
                    ->whereIn('id_user', $estudiantes))
                ->get();

            $resultado = [];
            foreach ($cursos as $curso) {
                $cantidadEstudiantes = \App\Models\Usuarios\Usuario::where('id_curso', $curso->id)
                    ->whereIn('id_user', $estudiantes)
                    ->count();

                $totalAtenciones = EnfermeriaAtencion::whereIn('id_user', fn($q) => $q->select('id_user')
                    ->from('usuarios')
                    ->where('id_curso', $curso->id))
                    ->when($fechaDesde, fn($q) => $q->whereDate('fechareg', '>=', $fechaDesde))
                    ->when($fechaHasta, fn($q) => $q->whereDate('fechareg', '<=', $fechaHasta))
                    ->count();

                $resultado[] = [
                    'curso_id' => $curso->id,
                    'curso_nombre' => $curso->nombre,
                    'total_atenciones' => $totalAtenciones,
                    'estudiantes_atendidos' => $cantidadEstudiantes,
                ];
            }

            usort($resultado, fn($a, $b) => $b['total_atenciones'] <=> $a['total_atenciones']);
            $resultado = array_slice($resultado, 0, $limite);

            return [
                'error' => false,
                'message' => 'Cursos con más atenciones obtenidos',
                'data' => $resultado,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener cursos con más atenciones');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas por curso',
                'data' => [],
            ];
        }
    }

    /**
     * Estudiantes con más atenciones médicas.
     */
    public function estudiantesConMasAtenciones(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $resultado = EnfermeriaAtencion::select(
                'id_user',
                DB::raw('COUNT(*) as total_atenciones'),
                DB::raw('MAX(fechareg) as ultima_atencion')
            )
                ->with('estudiante:id_user,nombre,apellido,documento,id_curso')
                ->when($fechaDesde, fn($q) => $q->whereDate('fechareg', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('fechareg', '<=', $fechaHasta))
                ->groupBy('id_user')
                ->orderByDesc('total_atenciones')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Estudiantes con más atenciones obtenidos',
                'data' => $resultado,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener estudiantes con más atenciones');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas por estudiante',
                'data' => [],
            ];
        }
    }

    /**
     * Categorías más registradas en atenciones médicas.
     */
    public function categoriasMasRegistradas(?string $fechaDesde = null, ?string $fechaHasta = null, ?int $limite = 10): array
    {
        try {
            $resultado = EnfermeriaAtencion::select(
                'motivo',
                DB::raw('COUNT(*) as total_registros')
            )
                ->with('categoria:id,nombre')
                ->when($fechaDesde, fn($q) => $q->whereDate('fechareg', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('fechareg', '<=', $fechaHasta))
                ->groupBy('motivo')
                ->orderByDesc('total_registros')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Categorías más registradas obtenidas',
                'data' => $resultado,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener categorías más registradas');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener estadísticas por categoría',
                'data' => [],
            ];
        }
    }
}

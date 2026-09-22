<?php

namespace App\Services\permisosLicencias;

use App\Models\PermisosLicencias\Permiso;
use App\Models\PermisosLicencias\PermisoInstitucional;
use App\Models\PermisosLicencias\PermisoLey;
use App\Models\PermisosLicencias\PermisoMotivo;
use App\Models\PermisosLicencias\PermisoPersonal;
use App\Models\PermisosLicencias\PermisoTipo;
use App\Models\Usuarios\Usuario;
use App\Services\FileStorageService;
use App\Services\MailService;
use Exception;
use Illuminate\Http\UploadedFile;

class PermisosLicenciasServices
{
    public const ESTADOS = [0 => 'Pendiente', 1 => 'Aprobado', 2 => 'Rechazado'];

    /**
     * Catálogos administrables desde Configuración — clave usada en la URL
     * (`/permisos-licencias/catalogos/{tipo}`). `campo_nombre` difiere entre
     * `permiso_motivo` (columna `nombre`) y los otros tres (`nombre_permiso`,
     * legado). `con_motivo` marca cuáles tienen la FK de aplicación `id_motivo`
     * hacia `permiso_motivo` (el motivo general no se vincula a sí mismo).
     */
    private const CATALOGOS = [
        'motivo' => ['model' => PermisoMotivo::class, 'campo_nombre' => 'nombre', 'con_motivo' => false],
        'ley' => ['model' => PermisoLey::class, 'campo_nombre' => 'nombre_permiso', 'con_motivo' => true],
        'personal' => ['model' => PermisoPersonal::class, 'campo_nombre' => 'nombre_permiso', 'con_motivo' => true],
        'institucional' => ['model' => PermisoInstitucional::class, 'campo_nombre' => 'nombre_permiso', 'con_motivo' => true],
    ];

    /** Perfiles a los que un usuario con la opción 92 puede asignarle un permiso (ver Permisos/index.php "operativos_check"). */
    public const PERFILES_ASIGNABLES = [10, 23, 27, 32];

    /** Perfiles cuyo correo también se notifica cuando alguien de su mismo nivel solicita/actualiza un permiso, y que ven el listado acotado a su nivel. */
    private const PERFILES_COORDINACION_NIVEL = [26, 7];

    public function __construct(
        private FileStorageService $fileStorage,
        private MailService $mailService,
    ) {
    }

    public function tipos(): array
    {
        try {
            return ['error' => false, 'message' => 'Tipos de permiso obtenidos correctamente', 'data' => PermisoTipo::where('activo', 1)->orderBy('id')->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function motivos(): array
    {
        try {
            return ['error' => false, 'message' => 'Motivos obtenidos correctamente', 'data' => PermisoMotivo::where('activo', 1)->orderBy('nombre')->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function catalogoLey(): array
    {
        try {
            return ['error' => false, 'message' => 'Catálogo obtenido correctamente', 'data' => PermisoLey::where('activo', 1)->orderBy('nombre_permiso')->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function catalogoPersonal(): array
    {
        try {
            return ['error' => false, 'message' => 'Catálogo obtenido correctamente', 'data' => PermisoPersonal::where('activo', 1)->orderBy('nombre_permiso')->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function catalogoInstitucional(): array
    {
        try {
            return ['error' => false, 'message' => 'Catálogo obtenido correctamente', 'data' => PermisoInstitucional::where('activo', 1)->orderBy('nombre_permiso')->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function usuariosAsignables(): array
    {
        try {
            $usuarios = Usuario::whereIn('perfil', self::PERFILES_ASIGNABLES)
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get(['id_user', 'nombre', 'apellido', 'perfil', 'id_nivel']);

            return ['error' => false, 'message' => 'Usuarios obtenidos correctamente', 'data' => $usuarios];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function misSolicitudes(int $idUser): array
    {
        try {
            $permisos = Permiso::with(['tipo:id,nombre', 'motivo:id,nombre'])
                ->where('id_user', $idUser)
                ->orderByDesc('id')
                ->get();

            $permisos->each(fn ($p) => $this->adjuntarUrl($p));

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $permisos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros, ?int $idNivelViewer, int $perPage): array
    {
        try {
            $query = Permiso::with(['usuario:id_user,nombre,apellido,documento,telefono,correo,id_nivel', 'tipo:id,nombre', 'motivo:id,nombre'])
                ->orderByDesc('id');

            // Coordinador/Directivo (perfil 26 o 7): acotado a las solicitudes de su propio nivel.
            if ($idNivelViewer !== null) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $idNivelViewer));
            }

            if (!empty($filtros['id_user'])) {
                $query->where('id_user', (int) $filtros['id_user']);
            }

            if (!empty($filtros['tipo_permiso'])) {
                $query->where('tipo_permiso', (int) $filtros['tipo_permiso']);
            }

            if (!empty($filtros['motivo_permiso'])) {
                $query->where('motivo_permiso', (int) $filtros['motivo_permiso']);
            }

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_permiso', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_permiso', '<=', $filtros['fecha_hasta']);
            }

            if (!empty($filtros['s'])) {
                $search = $filtros['s'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'like', "%{$search}%")
                            ->orWhere('apellido', 'like', "%{$search}%")
                            ->orWhere('documento', 'like', "%{$search}%"));
                });
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($p) => $this->adjuntarUrl($p));

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalle(int $id): array
    {
        try {
            $permiso = Permiso::with(['usuario:id_user,nombre,apellido,documento,telefono,correo,id_nivel', 'tipo:id,nombre', 'motivo:id,nombre'])->find($id);

            if (!$permiso) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $this->adjuntarUrl($permiso);

            return ['error' => false, 'message' => 'Solicitud obtenida correctamente', 'data' => $permiso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $data, int $idUser, int $idLog, ?UploadedFile $archivo): array
    {
        try {
            $evidencia = '';

            if ($archivo) {
                $subido = $this->fileStorage->uploadFile($archivo, 'permisos-licencias');
                $evidencia = $subido['ruta'];
            }

            $permiso = Permiso::create([
                'id_user' => $idUser,
                'tipo_permiso' => $data['tipo_permiso'],
                'motivo_permiso' => $data['motivo_permiso'],
                'fecha_permiso' => $data['fecha_permiso'] ?? null,
                'fecha_retorno' => $data['fecha_retorno'] ?? null,
                'dias_permiso' => $data['dias_permiso'] ?? null,
                'hora_salida' => $data['hora_salida'] ?? null,
                'tiempo_permiso' => $data['tiempo_permiso'] ?? null,
                'descripcion' => $data['descripcion'] ?? null,
                'tipo_permiso_detalle' => $data['tipo_permiso_detalle'] ?? '',
                'evidencia_permiso' => $evidencia,
                'id_log' => $idLog,
                'estado' => 0,
            ]);

            $this->notificarCreacion($permiso->fresh(['usuario', 'tipo', 'motivo']));

            return ['error' => false, 'message' => 'Solicitud registrada correctamente', 'data' => $permiso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, ?UploadedFile $archivo, int $idLog, bool $puedeEditarTodas, int $actorId): array
    {
        try {
            $permiso = Permiso::find($id);

            if (!$permiso) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($permiso->estado !== 0) {
                return ['error' => true, 'message' => 'Solo se pueden editar solicitudes pendientes', 'status' => 422];
            }

            if (!$puedeEditarTodas && $permiso->id_user !== $actorId) {
                return ['error' => true, 'message' => 'No puedes editar la solicitud de otro usuario', 'status' => 403];
            }

            $evidencia = $permiso->evidencia_permiso;

            if ($archivo) {
                $subido = $evidencia
                    ? $this->fileStorage->reemplazar($archivo, $evidencia, 'permisos-licencias')
                    : $this->fileStorage->uploadFile($archivo, 'permisos-licencias');
                $evidencia = $subido['ruta'];
            }

            $permiso->update([
                'fecha_permiso' => $data['fecha_permiso'] ?? $permiso->fecha_permiso,
                'fecha_retorno' => $data['fecha_retorno'] ?? $permiso->fecha_retorno,
                'dias_permiso' => $data['dias_permiso'] ?? $permiso->dias_permiso,
                'hora_salida' => $data['hora_salida'] ?? $permiso->hora_salida,
                'tiempo_permiso' => $data['tiempo_permiso'] ?? $permiso->tiempo_permiso,
                'descripcion' => $data['descripcion'] ?? $permiso->descripcion,
                'evidencia_permiso' => $evidencia,
                'id_edit' => $idLog,
                'fecha_edit' => now(),
            ]);

            $permiso = $permiso->fresh(['usuario', 'tipo', 'motivo']);
            $this->adjuntarUrl($permiso);

            return ['error' => false, 'message' => 'Solicitud actualizada correctamente', 'data' => $permiso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function aprobar(int $id, int $idLog, string $remunerado): array
    {
        return $this->cambiarEstado($id, 1, $idLog, null, $remunerado);
    }

    public function rechazar(int $id, int $idLog, string $motivo): array
    {
        return $this->cambiarEstado($id, 2, $idLog, $motivo, null);
    }

    private function cambiarEstado(int $id, int $estado, int $idLog, ?string $motivo, ?string $remunerado): array
    {
        try {
            $permiso = Permiso::with('usuario')->find($id);

            if (!$permiso) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($permiso->estado !== 0) {
                return ['error' => true, 'message' => 'Esta solicitud ya fue procesada', 'status' => 422];
            }

            $permiso->update([
                'estado' => $estado,
                'motivo_rechazo' => $motivo,
                'remunerado' => $remunerado,
                'id_edit' => $idLog,
                'fecha_edit' => now(),
            ]);

            $permiso = $permiso->fresh(['usuario', 'tipo', 'motivo']);
            $this->notificarCambioEstado($permiso);
            $this->adjuntarUrl($permiso);

            return ['error' => false, 'message' => 'Solicitud actualizada correctamente', 'data' => $permiso];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Enrutamiento de correo por perfil/nivel del beneficiario — mismo criterio que el
     * legado (ControlRecursos::solicitarPermisoControl/estadoPermisoControl), con dos
     * agregados explícitos: el coordinador (perfil 26) y el asistente (perfil 11) de
     * nivel del beneficiario siempre reciben la notificación, sin importar su perfil
     * puntual; y cualquier beneficiario de nivel 1, 2 o 3 también notifica al directivo
     * (perfil 7) de su propio nivel. Si el propio beneficiario es coordinador, no se
     * notifica a la asistente de nivel (no aplica notificarse a sí misma sobre su jefe).
     */
    private function destinatariosNotificacion(Permiso $permiso): array
    {
        $beneficiario = $permiso->usuario;
        $perfil = $beneficiario?->perfil;
        $idNivel = $beneficiario?->id_nivel;

        $destinatarios = array_merge(
            config('gestionHumana.correo_notificacion', []),
            [$beneficiario?->correo],
            Usuario::correosPorPerfilesYNivel($perfil === 26 ? [26] : [26, 11], $idNivel)
        );

        if ($idNivel === 1 || in_array($perfil, [23, 10, 32, 33], true) || $perfil === 11) {
            $destinatarios = array_merge($destinatarios, [
                config('gestionHumana.correo_asistente_direccion_administrativa'),
                config('gestionHumana.correo_direccion_administrativa'),
            ]);
        }

        if (in_array($idNivel, [1, 2, 3], true)) {
            $destinatarios = array_merge(
                $destinatarios,
                Usuario::correosPorPerfilesYNivel([7], $idNivel)
            );
        }

        return array_values(array_unique(array_filter($destinatarios)));
    }

    private function notificarCreacion(Permiso $permiso): void
    {
        $beneficiario = $permiso->usuario;
        $nombreCompleto = $beneficiario ? trim("{$beneficiario->nombre} {$beneficiario->apellido}") : "#{$permiso->id_user}";
        $tipo = $permiso->tipo?->nombre ?? 'Permiso';
        $motivo = $permiso->motivo?->nombre ?? '';
        $esParcial = $permiso->tipo_permiso === 1;

        $lineas = [
            "Se ha solicitado un permiso/licencia #{$permiso->id}, con la siguiente información:",
            '',
            "Usuario: {$nombreCompleto}",
            'Documento: ' . ($beneficiario->documento ?? 'N/A'),
            "Motivo: {$motivo}",
        ];

        if ($permiso->tipo_permiso_detalle) {
            $lineas[] = "Detalle del motivo: {$permiso->tipo_permiso_detalle}";
        }

        $lineas[] = "Tipo de permiso: {$tipo}";
        $lineas[] = 'Fecha del permiso: ' . ($permiso->fecha_permiso?->format('Y-m-d') ?? 'N/A');

        if ($permiso->hora_salida) {
            $lineas[] = "Hora de salida: {$permiso->hora_salida}";
        }

        if ($permiso->fecha_retorno) {
            $lineas[] = 'Día de ingreso: ' . $permiso->fecha_retorno->format('Y-m-d');
        }

        $lineas[] = $esParcial
            ? 'Tiempo aproximado del permiso: ' . ($permiso->tiempo_permiso ? "{$permiso->tiempo_permiso} minutos" : 'N/A')
            : 'Cantidad de días de permiso: ' . ($permiso->tiempo_permiso ?: ($permiso->dias_permiso ?: 'N/A'));

        $lineas[] = 'Descripción: ' . ($permiso->descripcion ?: 'N/A');
        $lineas[] = 'Evidencia adjunta: ' . ($permiso->evidencia_permiso ? 'Sí' : 'No');
        $lineas[] = 'Fecha de la solicitud: ' . ($permiso->fechareg?->format('Y-m-d H:i') ?? 'N/A');

        $this->mailService->sendGeneric($this->destinatariosNotificacion($permiso), 'Solicitud de permiso/licencia', implode("\n", $lineas));
    }

    private function notificarCambioEstado(Permiso $permiso): void
    {
        $beneficiario = $permiso->usuario;
        $nombreCompleto = $beneficiario ? trim("{$beneficiario->nombre} {$beneficiario->apellido}") : "#{$permiso->id_user}";
        $tipo = $permiso->tipo?->nombre ?? 'Permiso';
        $motivo = $permiso->motivo?->nombre ?? '';
        $estado = self::ESTADOS[$permiso->estado] ?? 'Actualizado';
        $esParcial = $permiso->tipo_permiso === 1;

        $lineas = [
            "La solicitud de permiso/licencia #{$permiso->id} ha sido {$estado}, con la siguiente información:",
            '',
            "Usuario: {$nombreCompleto}",
            'Documento: ' . ($beneficiario->documento ?? 'N/A'),
            "Motivo: {$motivo}",
        ];

        if ($permiso->tipo_permiso_detalle) {
            $lineas[] = "Detalle del motivo: {$permiso->tipo_permiso_detalle}";
        }

        $lineas[] = "Tipo de permiso: {$tipo}";
        $lineas[] = 'Fecha del permiso: ' . ($permiso->fecha_permiso?->format('Y-m-d') ?? 'N/A');

        if ($permiso->hora_salida) {
            $lineas[] = "Hora de salida: {$permiso->hora_salida}";
        }

        if ($permiso->fecha_retorno) {
            $lineas[] = 'Día de ingreso: ' . $permiso->fecha_retorno->format('Y-m-d');
        }

        $lineas[] = $esParcial
            ? 'Tiempo aproximado del permiso: ' . ($permiso->tiempo_permiso ? "{$permiso->tiempo_permiso} minutos" : 'N/A')
            : 'Cantidad de días de permiso: ' . ($permiso->tiempo_permiso ?: ($permiso->dias_permiso ?: 'N/A'));

        $lineas[] = 'Descripción: ' . ($permiso->descripcion ?: 'N/A');
        $lineas[] = 'Evidencia adjunta: ' . ($permiso->evidencia_permiso ? 'Sí' : 'No');
        $lineas[] = 'Fecha de la solicitud: ' . ($permiso->fechareg?->format('Y-m-d H:i') ?? 'N/A');
        $lineas[] = '';
        $lineas[] = "Estado: {$estado}";
        $lineas[] = 'Fecha de actualización: ' . ($permiso->fecha_edit?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'));

        if ($permiso->estado === 1 && $permiso->remunerado) {
            $lineas[] = 'Remunerado: ' . ($permiso->remunerado === 'si' ? 'Sí' : 'No');
        }

        if ($permiso->estado === 2 && $permiso->motivo_rechazo) {
            $lineas[] = "Motivo del rechazo: {$permiso->motivo_rechazo}";
        }

        $this->mailService->sendGeneric($this->destinatariosNotificacion($permiso), "Permiso/licencia - {$estado}", implode("\n", $lineas));
    }

    private function adjuntarUrl(Permiso $permiso): void
    {
        $permiso->url_evidencia = $permiso->evidencia_permiso ? $this->fileStorage->url($permiso->evidencia_permiso) : null;
    }

    // ── Configuración de catálogos (motivo/ley/personal/institucional) ─────

    public static function tipoCatalogoValido(string $tipo): bool
    {
        return isset(self::CATALOGOS[$tipo]);
    }

    public function listarCatalogoAdmin(string $tipo): array
    {
        try {
            $config = self::CATALOGOS[$tipo];
            $query = $config['model']::orderBy($config['campo_nombre']);

            if ($config['con_motivo']) {
                $query->with('motivo:id,nombre');
            }

            return ['error' => false, 'message' => 'Catálogo obtenido correctamente', 'data' => $query->get()];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearCatalogo(string $tipo, array $data): array
    {
        try {
            $config = self::CATALOGOS[$tipo];

            $item = $config['model']::create([
                $config['campo_nombre'] => $data['nombre'],
                ...($config['con_motivo'] ? ['id_motivo' => $data['id_motivo'] ?? null] : []),
            ]);

            if ($config['con_motivo']) {
                $item->load('motivo:id,nombre');
            }

            return ['error' => false, 'message' => 'Ítem creado correctamente', 'data' => $item];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarCatalogo(string $tipo, int $id, array $data): array
    {
        try {
            $config = self::CATALOGOS[$tipo];
            $item = $config['model']::find($id);

            if (!$item) {
                return ['error' => true, 'message' => 'No se encontró el ítem con id: ' . $id, 'status' => 404];
            }

            $item->update([
                $config['campo_nombre'] => $data['nombre'] ?? $item->{$config['campo_nombre']},
                ...($config['con_motivo'] && array_key_exists('id_motivo', $data) ? ['id_motivo' => $data['id_motivo']] : []),
            ]);

            $item = $item->fresh($config['con_motivo'] ? ['motivo:id,nombre'] : []);

            return ['error' => false, 'message' => 'Ítem actualizado correctamente', 'data' => $item];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function toggleEstadoCatalogo(string $tipo, int $id, bool $activo): array
    {
        try {
            $config = self::CATALOGOS[$tipo];
            $item = $config['model']::find($id);

            if (!$item) {
                return ['error' => true, 'message' => 'No se encontró el ítem con id: ' . $id, 'status' => 404];
            }

            $item->update(['activo' => $activo]);

            return ['error' => false, 'message' => $activo ? 'Ítem activado correctamente' : 'Ítem desactivado correctamente', 'data' => $item];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

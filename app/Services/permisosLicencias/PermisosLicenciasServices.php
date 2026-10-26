<?php

namespace App\Services\permisosLicencias;

use App\Enums\Mails;
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
use Illuminate\Database\Eloquent\Builder;
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

    /** Campos que `actualizar()` puede cambiar, con su etiqueta para el correo de edición. */
    private const CAMPOS_EDITABLES = [
        'fecha_permiso' => 'Fecha del permiso',
        'fecha_retorno' => 'Día de ingreso',
        'dias_permiso' => 'Cantidad de días de permiso',
        'hora_salida' => 'Hora de salida',
        'tiempo_permiso' => 'Tiempo del permiso',
        'descripcion' => 'Descripción',
        'evidencia_permiso' => 'Evidencia',
    ];

    public function __construct(
        private FileStorageService $fileStorage,
        private MailService $mailService,
    ) {}

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
            $paginator = $this->consultaFiltrada($filtros, $idNivelViewer)
                ->with(['usuario:id_user,nombre,apellido,documento,telefono,correo,id_nivel', 'tipo:id,nombre', 'motivo:id,nombre'])
                ->orderByDesc('id')
                ->paginate($perPage);
            $paginator->getCollection()->each(fn ($p) => $this->adjuntarUrl($p));

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Agregados para las gráficas del listado, con los mismos filtros que `listar()`.
     * Día completo (tipo 2): los días están en `dias_permiso` (legado) o en `tiempo_permiso`
     * (formulario nuevo). Parcial (tipo 1): minutos en `tiempo_permiso`. Las fechas
     * `0000-00-00` del legado cuentan en los totales pero no en las series por fecha.
     */
    public function estadisticas(array $filtros, ?int $idNivelViewer): array
    {
        try {
            $base = $this->consultaFiltrada($filtros, $idNivelViewer)->toBase();
            // ponytail: CAST al número inicial; texto sin número o negativo del legado cuenta 0.
            $dias = "SUM(CASE WHEN tipo_permiso = 2 THEN GREATEST(CAST(COALESCE(NULLIF(dias_permiso, ''), NULLIF(tiempo_permiso, ''), '0') AS SIGNED), 0) ELSE 0 END)";
            $horas = "ROUND(SUM(CASE WHEN tipo_permiso = 1 THEN GREATEST(CAST(COALESCE(NULLIF(tiempo_permiso, ''), '0') AS SIGNED), 0) ELSE 0 END) / 60, 1)";
            $porTipo = 'SUM(tipo_permiso = 1) AS parcial, SUM(tipo_permiso = 2) AS dia_completo';
            $conFecha = fn () => (clone $base)->where('fecha_permiso', '>', '1000-01-01');

            $resumen = (clone $base)->selectRaw(
                "COUNT(*) AS total, SUM(estado = 0) AS pendientes, SUM(estado = 1) AS aprobados, SUM(estado = 2) AS rechazados,
                COUNT(DISTINCT id_user) AS personas, {$dias} AS dias, {$horas} AS horas,
                SUM(estado = 1 AND remunerado = 'si') AS remunerados, SUM(estado = 1 AND remunerado = 'no') AS no_remunerados"
            )->first();

            $porMes = $conFecha()
                ->selectRaw("DATE_FORMAT(fecha_permiso, '%Y-%m') AS mes, {$porTipo}, COUNT(*) AS total, {$dias} AS dias, {$horas} AS horas")
                ->groupBy('mes')->orderBy('mes')->get();

            $motivos = PermisoMotivo::pluck('nombre', 'id');
            $porMotivo = (clone $base)
                ->selectRaw("motivo_permiso AS id, COUNT(*) AS total, {$dias} AS dias, {$horas} AS horas")
                ->groupBy('motivo_permiso')->orderByDesc('total')->get()
                ->map(fn ($f) => ['nombre' => $motivos[$f->id] ?? "Motivo #{$f->id}"] + (array) $f);

            $tipos = PermisoTipo::pluck('nombre', 'id');
            $porTipoPermiso = (clone $base)
                ->selectRaw("tipo_permiso AS id, COUNT(*) AS total, {$dias} AS dias, {$horas} AS horas")
                ->groupBy('tipo_permiso')->orderBy('tipo_permiso')->get()
                ->map(fn ($f) => ['nombre' => $tipos[$f->id] ?? "Tipo #{$f->id}"] + (array) $f);

            $porDetalle = (clone $base)->where('tipo_permiso_detalle', '<>', '')
                ->selectRaw('tipo_permiso_detalle AS nombre, COUNT(*) AS total')
                ->groupBy('tipo_permiso_detalle')->orderByDesc('total')->limit(10)->get();

            $porDiaSemana = $conFecha()
                ->selectRaw("DAYOFWEEK(fecha_permiso) AS dia, {$porTipo}")
                ->groupBy('dia')->orderBy('dia')->get();

            $porHoraSalida = (clone $base)->where('tipo_permiso', 1)->whereNotNull('hora_salida')
                ->selectRaw('HOUR(hora_salida) AS hora, COUNT(*) AS total')
                ->groupBy('hora')->orderBy('hora')->get();

            $top = (clone $base)
                ->selectRaw("id_user, COUNT(*) AS total, {$dias} AS dias, {$horas} AS horas")
                ->groupBy('id_user')->orderByDesc('total')->limit(10)->get();
            $nombres = Usuario::whereIn('id_user', $top->pluck('id_user'))->get(['id_user', 'nombre', 'apellido'])->keyBy('id_user');
            $topUsuarios = $top->map(fn ($f) => [
                'nombre' => isset($nombres[$f->id_user]) ? trim("{$nombres[$f->id_user]->nombre} {$nombres[$f->id_user]->apellido}") : "#{$f->id_user}",
                'total' => (int) $f->total,
                'dias' => (int) $f->dias,
                'horas' => (float) $f->horas,
            ]);

            return ['error' => false, 'message' => 'Estadísticas obtenidas correctamente', 'data' => [
                'resumen' => $resumen,
                'por_mes' => $porMes,
                'por_motivo' => $porMotivo,
                'por_tipo' => $porTipoPermiso,
                'por_detalle' => $porDetalle,
                'por_dia_semana' => $porDiaSemana,
                'por_hora_salida' => $porHoraSalida,
                'top_usuarios' => $topUsuarios,
            ]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Filtros comunes del listado y sus estadísticas (sin orden ni relaciones). */
    private function consultaFiltrada(array $filtros, ?int $idNivelViewer): Builder
    {
        $query = Permiso::query();

        // Coordinador/Directivo (perfil 26 o 7): acotado a las solicitudes de su propio nivel.
        if ($idNivelViewer !== null) {
            $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $idNivelViewer));
        }

        if (! empty($filtros['id_user'])) {
            $query->where('id_user', (int) $filtros['id_user']);
        }

        if (! empty($filtros['id_nivel'])) {
            $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', (int) $filtros['id_nivel']));
        }

        if (! empty($filtros['perfil'])) {
            $query->whereHas('usuario', fn ($u) => $u->where('perfil', (int) $filtros['perfil']));
        }

        if (! empty($filtros['tipo_permiso'])) {
            $query->where('tipo_permiso', (int) $filtros['tipo_permiso']);
        }

        if (! empty($filtros['motivo_permiso'])) {
            $query->where('motivo_permiso', (int) $filtros['motivo_permiso']);
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            $query->where('estado', (int) $filtros['estado']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_permiso', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_permiso', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['s'])) {
            $search = $filtros['s'];
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellido', 'like', "%{$search}%")
                        ->orWhere('documento', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function detalle(int $id): array
    {
        try {
            $permiso = Permiso::with(['usuario:id_user,nombre,apellido,documento,telefono,correo,id_nivel', 'tipo:id,nombre', 'motivo:id,nombre'])->find($id);

            if (! $permiso) {
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

            if (! $permiso) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($permiso->estado !== 0) {
                return ['error' => true, 'message' => 'Solo se pueden editar solicitudes pendientes', 'status' => 422];
            }

            if (! $puedeEditarTodas && $permiso->id_user !== $actorId) {
                return ['error' => true, 'message' => 'No puedes editar la solicitud de otro usuario', 'status' => 403];
            }

            $antes = $permiso->only(array_keys(self::CAMPOS_EDITABLES));
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

            // Sin id_edit/fecha_edit, que cambian siempre: solo lo que el usuario modificó.
            $cambios = array_intersect_key($permiso->getChanges(), $antes);

            $permiso = $permiso->fresh(['usuario', 'tipo', 'motivo']);

            if ($cambios) {
                $this->notificarEdicion($permiso, $antes, $cambios, $actorId);
            }

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

            if (! $permiso) {
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
     * Gestión Humana sale del grupo `GESTION_HUMANA` de `correos_institucionales`, y si
     * otro usuario registró el permiso a nombre del beneficiario (opción 92), también se
     * le notifica a él.
     */
    private function destinatariosNotificacion(Permiso $permiso): array
    {
        $beneficiario = $permiso->usuario;
        $perfil = $beneficiario?->perfil;
        $idNivel = $beneficiario?->id_nivel;

        $destinatarios = array_merge(
            Mails::GESTION_HUMANA->recipients(),
            [$beneficiario?->correo],
            Usuario::correosPorPerfilesYNivel($perfil === 26 ? [26] : [26, 11], $idNivel)
        );

        if ($permiso->id_log && $permiso->id_log !== $permiso->id_user) {
            $destinatarios[] = Usuario::find($permiso->id_log)?->correo;
        }

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
        $lineas = [
            "Se ha solicitado un permiso/licencia #{$permiso->id}, con la siguiente información:",
            '',
            ...$this->lineasDetalle($permiso),
        ];

        $this->mailService->sendGeneric($this->destinatariosNotificacion($permiso), 'Solicitud de permiso/licencia', implode("\n", $lineas));
    }

    private function notificarCambioEstado(Permiso $permiso): void
    {
        $estado = self::ESTADOS[$permiso->estado] ?? 'Actualizado';

        $lineas = [
            "La solicitud de permiso/licencia #{$permiso->id} ha sido {$estado}, con la siguiente información:",
            '',
            ...$this->lineasDetalle($permiso),
            '',
            "Estado: {$estado}",
            'Fecha de actualización: '.($permiso->fecha_edit?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')),
        ];

        if ($permiso->estado === 1 && $permiso->remunerado) {
            $lineas[] = 'Remunerado: '.($permiso->remunerado === 'si' ? 'Sí' : 'No');
        }

        if ($permiso->estado === 2 && $permiso->motivo_rechazo) {
            $lineas[] = "Motivo del rechazo: {$permiso->motivo_rechazo}";
        }

        $this->mailService->sendGeneric($this->destinatariosNotificacion($permiso), "Permiso/licencia - {$estado}", implode("\n", $lineas));
    }

    /**
     * @param  array  $antes  valores (casteados) de CAMPOS_EDITABLES antes del update
     * @param  array  $cambios  `getChanges()` acotado a CAMPOS_EDITABLES (solo se usan las claves)
     */
    private function notificarEdicion(Permiso $permiso, array $antes, array $cambios, int $actorId): void
    {
        $actor = Usuario::find($actorId);
        $nombreActor = $actor ? trim("{$actor->nombre} {$actor->apellido}") : "#{$actorId}";

        $lineas = [
            "La solicitud de permiso/licencia #{$permiso->id} ha sido editada por {$nombreActor}.",
            '',
            'Cambios realizados:',
        ];

        foreach (array_keys($cambios) as $campo) {
            $etiqueta = self::CAMPOS_EDITABLES[$campo];

            // Nunca exponer la ruta del archivo en el correo.
            $lineas[] = $campo === 'evidencia_permiso'
                ? "{$etiqueta}: ".($antes[$campo] ? 'se reemplazó el archivo' : 'se adjuntó un archivo')
                : "{$etiqueta}: {$this->valorCorreo($antes[$campo])} → {$this->valorCorreo($permiso->{$campo})}";
        }

        $lineas = array_merge($lineas, [
            '',
            'Información vigente:',
            ...$this->lineasDetalle($permiso),
            '',
            'Fecha de actualización: '.($permiso->fecha_edit?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')),
        ]);

        $this->mailService->sendGeneric($this->destinatariosNotificacion($permiso), 'Permiso/licencia - Editada', implode("\n", $lineas));
    }

    private function valorCorreo(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return ($valor === null || $valor === '') ? 'N/A' : (string) $valor;
    }

    /** Bloque común de datos de la solicitud para los correos de creación, cambio de estado y edición. */
    private function lineasDetalle(Permiso $permiso): array
    {
        $beneficiario = $permiso->usuario;
        $nombreCompleto = $beneficiario ? trim("{$beneficiario->nombre} {$beneficiario->apellido}") : "#{$permiso->id_user}";
        $tipo = $permiso->tipo?->nombre ?? 'Permiso';
        $motivo = $permiso->motivo?->nombre ?? '';
        $esParcial = $permiso->tipo_permiso === 1;

        $lineas = [
            "Usuario: {$nombreCompleto}",
            'Documento: '.($beneficiario->documento ?? 'N/A'),
            "Motivo: {$motivo}",
        ];

        if ($permiso->tipo_permiso_detalle) {
            $lineas[] = "Detalle del motivo: {$permiso->tipo_permiso_detalle}";
        }

        $lineas[] = "Tipo de permiso: {$tipo}";
        $lineas[] = 'Fecha del permiso: '.($permiso->fecha_permiso?->format('Y-m-d') ?? 'N/A');

        if ($permiso->hora_salida) {
            $lineas[] = "Hora de salida: {$permiso->hora_salida}";
        }

        if ($permiso->fecha_retorno) {
            $lineas[] = 'Día de ingreso: '.$permiso->fecha_retorno->format('Y-m-d');
        }

        $lineas[] = $esParcial
            ? 'Tiempo aproximado del permiso: '.($permiso->tiempo_permiso ? "{$permiso->tiempo_permiso} minutos" : 'N/A')
            : 'Cantidad de días de permiso: '.($permiso->tiempo_permiso ?: ($permiso->dias_permiso ?: 'N/A'));

        $lineas[] = 'Descripción: '.($permiso->descripcion ?: 'N/A');
        $lineas[] = 'Evidencia adjunta: '.($permiso->evidencia_permiso ? 'Sí' : 'No');
        $lineas[] = 'Fecha de la solicitud: '.($permiso->fechareg?->format('Y-m-d H:i') ?? 'N/A');

        return $lineas;
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

            if (! $item) {
                return ['error' => true, 'message' => 'No se encontró el ítem con id: '.$id, 'status' => 404];
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

            if (! $item) {
                return ['error' => true, 'message' => 'No se encontró el ítem con id: '.$id, 'status' => 404];
            }

            $item->update(['activo' => $activo]);

            return ['error' => false, 'message' => $activo ? 'Ítem activado correctamente' : 'Ítem desactivado correctamente', 'data' => $item];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

<?php

namespace App\Services\Carnets;

use App\Models\Carnets\Carnet;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\FileStorageService;
use App\Services\Noticias\CumpleanosImagen;
use App\Services\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Carnets institucionales de estudiantes y trabajadores: listado con su último carnet,
 * emisión (cada impresión es una versión nueva), control de entregas y fotos
 * (`usuarios.foto_carnet`). El código de barras del carnet es el documento (CODE 128, el
 * mismo que lee "Escanear carnets" de Llegadas tarde).
 */
class CarnetService extends Service
{
    public const PERFIL_ESTUDIANTE = 16;

    /** Acudiente, Proveedor y Egresado no llevan carnet; todo otro perfil es "trabajador". */
    public const PERFILES_SIN_CARNET = [6, 17, 28];

    /** Carpeta de las fotos subidas desde este módulo: solo estas se borran al reemplazarlas. */
    private const CARPETA_FOTOS = 'carnets';

    /** Estados de un carnet impreso que todavía no se entrega (REIMPRESO = versión 2 en adelante). */
    public const POR_ENTREGAR = ['IMPRESO', 'REIMPRESO'];

    /** Desde cada estado, a cuáles se puede pasar a mano. PERDIDO y ANULADO son finales. */
    private const TRANSICIONES = [
        'IMPRESO' => ['ENTREGADO', 'PERDIDO', 'ANULADO'],
        'REIMPRESO' => ['ENTREGADO', 'PERDIDO', 'ANULADO'],
        'ENTREGADO' => ['PERDIDO', 'ANULADO'],
    ];

    public function __construct(
        private FileStorageService $archivos,
        private AnioEscolarServices $anios,
        private CarnetPlantillaService $plantillas,
    ) {
    }

    public static function transicionPermitida(string $actual, string $nuevo): bool
    {
        return in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true);
    }

    /** "1.234.567 .JPG" → "1234567": el nombre del archivo, sin extensión ni separadores. */
    public static function documentoDeArchivo(string $nombreArchivo): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', pathinfo($nombreArchivo, PATHINFO_FILENAME));
    }

    /** Usuarios activos que llevan carnet; `tipo`: estudiantes | trabajadores | null (ambos). */
    private function poblacion(?string $tipo)
    {
        return Usuario::query()
            ->where('usuarios.estado', 'activo')
            ->whereNotIn('usuarios.perfil', self::PERFILES_SIN_CARNET)
            ->when($tipo === 'estudiantes', fn ($q) => $q->where('usuarios.perfil', self::PERFIL_ESTUDIANTE))
            ->when($tipo === 'trabajadores', fn ($q) => $q->where('usuarios.perfil', '!=', self::PERFIL_ESTUDIANTE));
    }

    public function listar(array $filtros): array
    {
        try {
            $ultimo = DB::table('carnets')->selectRaw('id_user, MAX(id) AS id_ultimo')->groupBy('id_user');
            $sort = in_array($filtros['sort'] ?? '', ['nombre', 'apellido', 'documento'], true) ? $filtros['sort'] : 'nombre';
            $dir = strtolower($filtros['dir'] ?? '') === 'desc' ? 'desc' : 'asc';

            $usuarios = $this->poblacion($filtros['tipo'] ?? 'estudiantes')
                ->leftJoinSub($ultimo, 'cu', 'cu.id_user', '=', 'usuarios.id_user')
                ->leftJoin('carnets as c', 'c.id', '=', 'cu.id_ultimo')
                ->select([
                    'usuarios.id_user', 'usuarios.documento', 'usuarios.nombre', 'usuarios.apellido',
                    'usuarios.perfil', 'usuarios.id_nivel', 'usuarios.id_curso', 'usuarios.foto_carnet',
                    'c.id as carnet_id', 'c.version as carnet_version', 'c.estado as carnet_estado',
                    'c.fecha_emision as carnet_fecha_emision', 'c.fecha_entrega as carnet_fecha_entrega',
                ])
                ->with('nivelRelacion:id,nombre')
                ->when($filtros['cursos'] ?? null, fn ($q, $cursos) => $q->whereIn('usuarios.id_curso', (array) $cursos))
                ->when($filtros['estado_carnet'] ?? null, fn ($q, $estado) => $estado === 'SIN_CARNET'
                    ? $q->whereNull('c.id')
                    : $q->where('c.estado', $estado))
                ->when(!empty($filtros['sin_foto']), fn ($q) => $q->where(fn ($s) => $s->whereNull('usuarios.foto_carnet')->orWhere('usuarios.foto_carnet', '')))
                ->when($filtros['s'] ?? null, function ($q, $busqueda) {
                    foreach (preg_split('/\s+/', trim($busqueda)) as $palabra) {
                        $q->where(fn ($s) => $s->where('usuarios.nombre', 'LIKE', "%$palabra%")
                            ->orWhere('usuarios.apellido', 'LIKE', "%$palabra%")
                            ->orWhere('usuarios.documento', 'LIKE', "%$palabra%"));
                    }
                })
                ->orderBy("usuarios.$sort", $dir)
                ->paginate((int) ($filtros['per_page'] ?? 10));

            $usuarios->getCollection()->transform(fn ($u) => [
                'id_user' => $u->id_user,
                'documento' => $u->documento,
                'nombre' => $u->nombre,
                'apellido' => $u->apellido,
                'detalle' => $this->detalle($u),
                'foto' => $u->foto_carnet ?: null,
                'carnet' => $u->carnet_id ? [
                    'id' => $u->carnet_id,
                    'version' => (int) $u->carnet_version,
                    'estado' => $u->carnet_estado,
                    'fecha_emision' => $u->carnet_fecha_emision,
                    'fecha_entrega' => $u->carnet_fecha_entrega,
                ] : null,
            ]);

            return ['error' => false, 'message' => 'Carnets obtenidos', 'data' => $usuarios];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al listar carnets');
            return ['error' => true, 'message' => 'Error al listar los carnets.'];
        }
    }

    /** Nivel del usuario (Preescolar, Primaria, Administrativo…), estudiante o trabajador: la línea bajo el nombre en el carnet. */
    private function detalle(Usuario $u): ?string
    {
        return $u->nivelRelacion?->nombre;
    }

    /**
     * Lo que se dibuja en el carnet (impreso o digital). `plantillas`: las en uso por tipo
     * (CarnetPlantillaService::enUso); sin plantilla, el frontend usa el diseño por defecto.
     * `nombres`: el nombre en cada formato de plantilla, limpio de datos legados como en cumpleaños.
     */
    private function datosCarnet(Usuario $u, ?Carnet $carnet, ?string $vigencia, array $plantillas): array
    {
        $tipo = $u->perfil === self::PERFIL_ESTUDIANTE ? 'estudiante' : 'trabajador';

        return [
            'id_user' => $u->id_user,
            'nombre' => $u->nombre,
            'apellido' => $u->apellido,
            'nombres' => collect(['completo', 'nombre_apellido', 'primer_nombre'])
                ->mapWithKeys(fn ($f) => [$f => CumpleanosImagen::formatearNombre($u->nombre, $u->apellido, $f)])
                ->all(),
            'documento' => $u->documento,
            'tipo' => $tipo,
            'plantilla' => $plantillas[$tipo] ?? null,
            'detalle' => $this->detalle($u),
            'foto' => $u->foto_carnet ?: null,
            'vigencia' => $vigencia,
            'carnet' => $carnet ? [
                'id' => $carnet->id,
                'version' => $carnet->version,
                'estado' => $carnet->estado,
                'fecha_emision' => $carnet->fecha_emision?->format('Y-m-d H:i:s'),
                'fecha_entrega' => $carnet->fecha_entrega?->format('Y-m-d H:i:s'),
            ] : null,
        ];
    }

    private function vigencia(): ?string
    {
        $anio = $this->anios->obtenerUltimoAnioEscolar()['data'] ?? null;

        return $anio ? "{$anio->anio_inicio} - {$anio->anio_fin}" : null;
    }

    /**
     * Emite (imprime) un carnet nuevo por usuario: versión siguiente, en IMPRESO si es el primero
     * o REIMPRESO si ya tenía uno. El vigente anterior (por entregar o ENTREGADO) queda ANULADO.
     * Todos deben tener foto.
     */
    public function emitir(array $ids, int $idUsuarioEmite): array
    {
        $usuarios = $this->usuariosParaImprimir($ids);
        if (is_string($usuarios)) {
            return ['error' => true, 'message' => $usuarios];
        }

        try {
            $ahora = now();
            $carnets = DB::transaction(fn () => $usuarios->mapWithKeys(function ($u) use ($ahora, $idUsuarioEmite) {
                $anterior = Carnet::where('id_user', $u->id_user)->latest('id')->lockForUpdate()->first();

                if ($anterior && in_array($anterior->estado, [...self::POR_ENTREGAR, 'ENTREGADO'], true)) {
                    $anterior->update([
                        'estado' => 'ANULADO',
                        'fecha_estado' => $ahora,
                        'observacion' => 'Reemplazado por la versión ' . ($anterior->version + 1),
                        'id_usuario_estado' => $idUsuarioEmite,
                    ]);
                }

                return [$u->id_user => Carnet::create([
                    'id_user' => $u->id_user,
                    'version' => ($anterior?->version ?? 0) + 1,
                    'estado' => $anterior ? 'REIMPRESO' : 'IMPRESO',
                    'fecha_emision' => $ahora,
                    'id_usuario_emite' => $idUsuarioEmite,
                ])];
            }));

            $vigencia = $this->vigencia();
            $plantillas = $this->plantillas->enUso();

            return [
                'error' => false,
                'message' => 'Carnets emitidos',
                'data' => $usuarios->map(fn ($u) => $this->datosCarnet($u, $carnets[$u->id_user], $vigencia, $plantillas))->values(),
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al emitir carnets');
            return ['error' => true, 'message' => 'Error al emitir los carnets.'];
        }
    }

    /**
     * Lo que saldría impreso, sin emitir nada: mismas validaciones y mismos datos que emitir,
     * con `carnet` = el último carnet de cada persona (el que se reemplazaría).
     */
    public function vistaPrevia(array $ids): array
    {
        $usuarios = $this->usuariosParaImprimir($ids);
        if (is_string($usuarios)) {
            return ['error' => true, 'message' => $usuarios];
        }

        $ultimos = Carnet::whereIn('id', Carnet::whereIn('id_user', $ids)->selectRaw('MAX(id)')->groupBy('id_user'))->get()->keyBy('id_user');
        $vigencia = $this->vigencia();
        $plantillas = $this->plantillas->enUso();

        return [
            'error' => false,
            'message' => 'Vista previa',
            'data' => $usuarios->map(fn ($u) => $this->datosCarnet($u, $ultimos[$u->id_user] ?? null, $vigencia, $plantillas))->values(),
        ];
    }

    /** Usuarios a imprimir en el orden de impresión, o el mensaje de por qué no se puede. Todos deben tener foto. */
    private function usuariosParaImprimir(array $ids): \Illuminate\Support\Collection|string
    {
        $usuarios = $this->poblacion(null)
            ->whereIn('id_user', $ids)
            ->with('nivelRelacion:id,nombre')
            ->orderBy('apellido')->orderBy('nombre')
            ->get();

        if ($usuarios->count() !== count(array_unique($ids))) {
            return 'Alguno de los usuarios no existe, está inactivo o no lleva carnet.';
        }

        $sinFoto = $usuarios->filter(fn ($u) => !$u->foto_carnet);
        if ($sinFoto->isNotEmpty()) {
            return 'Sin foto de carnet: ' . $sinFoto->map(fn ($u) => trim("$u->nombre $u->apellido"))->join(', ') . '.';
        }

        return $usuarios->toBase();
    }

    /** Entregado, perdido o anulado. Solo sobre el último carnet del usuario. */
    public function cambiarEstado(int $idCarnet, string $estado, ?string $observacion, int $idUsuario): array
    {
        $carnet = Carnet::find($idCarnet);
        if (!$carnet) {
            return ['error' => true, 'message' => 'El carnet no existe.', 'status' => 404];
        }

        if (Carnet::where('id_user', $carnet->id_user)->where('id', '>', $carnet->id)->exists()) {
            return ['error' => true, 'message' => 'Este carnet ya fue reemplazado por una versión más reciente.', 'status' => 422];
        }

        if (!self::transicionPermitida($carnet->estado, $estado)) {
            return ['error' => true, 'message' => "Un carnet {$carnet->estado} no puede pasar a {$estado}.", 'status' => 422];
        }

        $ahora = now();
        $carnet->update([
            'estado' => $estado,
            'fecha_estado' => $ahora,
            'fecha_entrega' => $estado === 'ENTREGADO' ? $ahora : $carnet->fecha_entrega,
            'observacion' => $observacion,
            'id_usuario_estado' => $idUsuario,
        ]);

        return ['error' => false, 'message' => 'Estado del carnet actualizado', 'data' => $carnet->fresh()];
    }

    /** Entrega en lote: el último carnet de cada usuario, si está por entregar (IMPRESO o REIMPRESO). Los demás se omiten. */
    public function entregar(array $idsUsuario, int $idUsuario): array
    {
        // pluck: MySQL no deja un UPDATE con subconsulta sobre la misma tabla (error 1093).
        $ultimos = DB::table('carnets')->selectRaw('MAX(id) AS id')->whereIn('id_user', $idsUsuario)->groupBy('id_user')->pluck('id');
        $ahora = now();

        $entregados = Carnet::whereIn('id', $ultimos)->whereIn('estado', self::POR_ENTREGAR)->update([
            'estado' => 'ENTREGADO',
            'fecha_entrega' => $ahora,
            'fecha_estado' => $ahora,
            'id_usuario_estado' => $idUsuario,
        ]);

        return ['error' => false, 'message' => "Carnets entregados: $entregados", 'data' => ['entregados' => $entregados]];
    }

    public function historial(int $idUser): array
    {
        $carnets = Carnet::query()
            ->from('carnets as c')
            ->leftJoin('usuarios as e', 'e.id_user', '=', 'c.id_usuario_emite')
            ->leftJoin('usuarios as m', 'm.id_user', '=', 'c.id_usuario_estado')
            ->where('c.id_user', $idUser)
            ->orderByDesc('c.id')
            ->get([
                'c.*',
                DB::raw("TRIM(CONCAT(COALESCE(e.nombre, ''), ' ', COALESCE(e.apellido, ''))) as emitido_por"),
                DB::raw("TRIM(CONCAT(COALESCE(m.nombre, ''), ' ', COALESCE(m.apellido, ''))) as estado_por"),
            ]);

        return ['error' => false, 'message' => 'Historial obtenido', 'data' => $carnets];
    }

    /** Reemplaza la foto de carnet. Solo borra la anterior si la subió este módulo. */
    public function subirFoto(int $idUser, UploadedFile $archivo): array
    {
        $usuario = $this->poblacion(null)->find($idUser);
        if (!$usuario) {
            return ['error' => true, 'message' => 'El usuario no existe, está inactivo o no lleva carnet.', 'status' => 404];
        }

        try {
            $this->guardarFoto($usuario, $archivo);

            return ['error' => false, 'message' => 'Foto actualizada', 'data' => ['foto' => $usuario->foto_carnet]];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al subir la foto de carnet');
            return ['error' => true, 'message' => 'No se pudo guardar la foto.'];
        }
    }

    private function guardarFoto(Usuario $usuario, UploadedFile $archivo): void
    {
        $anterior = $usuario->foto_carnet;
        $usuario->foto_carnet = $this->archivos->uploadFile($archivo, self::CARPETA_FOTOS)['ruta'];
        $usuario->save();

        // ponytail: las fotos legadas (en la raíz del disco) no se borran; pueden estar en uso en otra parte.
        if ($anterior && str_starts_with($anterior, self::CARPETA_FOTOS . '/')) {
            $this->archivos->eliminar($anterior);
        }
    }

    /**
     * Fotos en lote: cada archivo se llama como el documento del usuario
     * (`1234567.jpg`). Devuelve qué se asignó y qué no se pudo asignar.
     *
     * @param UploadedFile[] $archivos
     */
    public function subirFotosLote(array $archivos): array
    {
        $porDocumento = collect($archivos)->keyBy(fn ($a) => self::documentoDeArchivo($a->getClientOriginalName()));
        $usuarios = $this->poblacion(null)->whereIn('documento', $porDocumento->keys())->get()->groupBy('documento');

        $asignadas = [];
        $sinAsignar = [];

        foreach ($porDocumento as $documento => $archivo) {
            $coincidencias = $usuarios->get($documento, collect());

            if ($coincidencias->count() !== 1) {
                $sinAsignar[] = [
                    'archivo' => $archivo->getClientOriginalName(),
                    'motivo' => $coincidencias->isEmpty()
                        ? 'Ningún estudiante o trabajador activo tiene ese documento.'
                        : 'Hay varios usuarios activos con ese documento.',
                ];
                continue;
            }

            $usuario = $coincidencias->first();
            try {
                $this->guardarFoto($usuario, $archivo);
                $asignadas[] = ['archivo' => $archivo->getClientOriginalName(), 'nombre' => trim("$usuario->nombre $usuario->apellido")];
            } catch (\Exception $e) {
                $this->sendError($e, 'Error al subir una foto de carnet en lote');
                $sinAsignar[] = ['archivo' => $archivo->getClientOriginalName(), 'motivo' => 'No se pudo guardar la foto.'];
            }
        }

        return ['error' => false, 'message' => 'Fotos procesadas', 'data' => ['asignadas' => $asignadas, 'sin_asignar' => $sinAsignar]];
    }

    /** Carnet digital del usuario en sesión (null si su perfil no lleva carnet). */
    public function miCarnet(int $idUser): array
    {
        $usuario = $this->poblacion(null)
            ->with('nivelRelacion:id,nombre')
            ->find($idUser);

        if (!$usuario) {
            return ['error' => false, 'message' => 'Este perfil no lleva carnet', 'data' => null];
        }

        $carnet = Carnet::where('id_user', $idUser)->latest('id')->first();

        return ['error' => false, 'message' => 'Carnet obtenido', 'data' => $this->datosCarnet($usuario, $carnet, $this->vigencia(), $this->plantillas->enUso())];
    }
}

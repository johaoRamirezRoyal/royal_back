<?php

namespace App\Services\tramites;

use App\Models\Tramites\Eps;
use App\Models\Tramites\Tramite;
use App\Models\Tramites\TramiteDocumento;
use App\Models\Tramites\TramiteFamiliar;
use App\Models\Tramites\TramiteGrupoFamiliar;
use App\Models\Tramites\TramiteTipo;
use App\Models\Usuarios\Usuario;
use App\Services\FileStorageService;
use App\Services\MailService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TramitesServices
{
    public const ESTADOS = [0 => 'Pendiente', 1 => 'Finalizado', 2 => 'Rechazado'];

    /** Campos propios de `tramite` que puede traer el payload de creación — el resto (estado, id_log, etc.) lo resuelve el servicio. */
    private const CAMPOS_TRAMITE = [
        'tipo_tramite', 'motivo', 'mencion_certificado', 'otra', 'entidad_certificado',
        'correo', 'modo_entrega', 'anio_grabable', 'eps_actual', 'eps_traslado', 'eps_grupo',
        'fecha_inicio', 'fecha_fin',
    ];

    public function __construct(
        private FileStorageService $fileStorage,
        private MailService $mailService,
    ) {
    }

    public function tipos(): array
    {
        try {
            $tipos = TramiteTipo::where('activo', 1)->orderBy('nombre')->get();

            return ['error' => false, 'message' => 'Tipos de trámite obtenidos correctamente', 'data' => $tipos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function gruposFamiliares(): array
    {
        try {
            $grupos = TramiteGrupoFamiliar::where('activo', 1)->orderBy('nombre')->get();

            return ['error' => false, 'message' => 'Catálogo obtenido correctamente', 'data' => $grupos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function epsCatalogo(): array
    {
        try {
            $eps = Eps::orderBy('nombre')->get();

            return ['error' => false, 'message' => 'Catálogo de EPS obtenido correctamente', 'data' => $eps];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function misTramites(int $idUser): array
    {
        try {
            $tramites = Tramite::with(['tipo:id,nombre', 'documentos', 'familiares.grupo:id,nombre'])
                ->where('id_user', $idUser)
                ->orderByDesc('id')
                ->get();

            $tramites->each(fn ($t) => $t->documentos->each(fn ($d) => $d->url_documento = $this->fileStorage->url($d->archivo)));

            return ['error' => false, 'message' => 'Trámites obtenidos correctamente', 'data' => $tramites];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros, int $perPage): array
    {
        try {
            $query = Tramite::with(['usuario:id_user,nombre,apellido,documento,telefono', 'tipo:id,nombre'])
                ->orderByDesc('id');

            if (!empty($filtros['id_user'])) {
                $query->where('id_user', (int) $filtros['id_user']);
            }

            if (!empty($filtros['tipo_tramite'])) {
                $query->where('tipo_tramite', (int) $filtros['tipo_tramite']);
            }

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
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

            return ['error' => false, 'message' => 'Trámites obtenidos correctamente', 'data' => $query->paginate($perPage)];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalle(int $id): array
    {
        try {
            $tramite = Tramite::with([
                'usuario:id_user,nombre,apellido,documento,telefono,correo',
                'tipo:id,nombre',
                'documentos',
                'familiares.grupo:id,nombre',
            ])->find($id);

            if (!$tramite) {
                return ['error' => true, 'message' => 'Trámite no encontrado', 'status' => 404];
            }

            $tramite->documentos->each(fn ($d) => $d->url_documento = $this->fileStorage->url($d->archivo));

            return ['error' => false, 'message' => 'Trámite obtenido correctamente', 'data' => $tramite];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<int, UploadedFile> $archivos
     * @param array<int, int> $grupoFamiliarIds
     */
    public function crear(array $data, array $archivos, array $grupoFamiliarIds, Usuario $solicitante, int $idLog): array
    {
        try {
            $tramite = DB::transaction(function () use ($data, $archivos, $grupoFamiliarIds, $solicitante, $idLog) {
                $tramite = Tramite::create([
                    ...array_intersect_key($data, array_flip(self::CAMPOS_TRAMITE)),
                    'id_user' => $solicitante->id_user,
                    'id_log' => $idLog,
                    'estado' => 0,
                ]);

                foreach ($grupoFamiliarIds as $idGrupo) {
                    TramiteFamiliar::create([
                        'id_tramite' => $tramite->id,
                        'grupo_familiar' => $idGrupo,
                        'id_log' => $idLog,
                    ]);
                }

                foreach ($archivos as $archivo) {
                    $subido = $this->fileStorage->uploadFile($archivo, 'tramites/documentos');
                    TramiteDocumento::create([
                        'id_tramite' => $tramite->id,
                        'archivo' => $subido['ruta'],
                        'id_log' => $idLog,
                    ]);
                }

                return $tramite;
            });

            $this->notificarTramiteCreado($tramite->fresh('tipo'), $solicitante);

            return ['error' => false, 'message' => 'Trámite solicitado correctamente', 'data' => $tramite];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function finalizar(int $id, int $idLog, ?UploadedFile $archivo): array
    {
        return $this->cambiarEstado($id, 1, $idLog, null, $archivo);
    }

    public function rechazar(int $id, int $idLog, string $motivo): array
    {
        return $this->cambiarEstado($id, 2, $idLog, $motivo, null);
    }

    private function cambiarEstado(int $id, int $estado, int $idLog, ?string $motivo, ?UploadedFile $archivo): array
    {
        try {
            $tramite = Tramite::with('usuario')->find($id);

            if (!$tramite) {
                return ['error' => true, 'message' => 'Trámite no encontrado', 'status' => 404];
            }

            $tramite->update([
                'estado' => $estado,
                'id_edit' => $idLog,
                'fecha_edit' => now(),
                'motivo_rechazo' => $motivo,
            ]);

            if ($archivo) {
                $subido = $this->fileStorage->uploadFile($archivo, 'tramites/documentos');
                TramiteDocumento::create([
                    'id_tramite' => $tramite->id,
                    'archivo' => $subido['ruta'],
                    'id_log' => $idLog,
                ]);
            }

            $this->notificarCambioEstado($tramite->fresh(['usuario', 'tipo']));

            return ['error' => false, 'message' => 'Trámite actualizado correctamente', 'data' => $tramite->fresh(['usuario', 'tipo', 'documentos'])];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function notificarTramiteCreado(Tramite $tramite, Usuario $solicitante): void
    {
        $tipo = $tramite->tipo?->nombre ?? 'Trámite';
        $nombreCompleto = trim("{$solicitante->nombre} {$solicitante->apellido}");
        $contenido = "El usuario {$nombreCompleto} (documento {$solicitante->documento}) ha solicitado el trámite #{$tramite->id} ({$tipo}).";

        $destinatarios = array_values(array_unique(array_filter(array_merge(
            config('gestionHumana.correo_notificacion', []),
            [$solicitante->correo]
        ))));

        $this->mailService->sendGeneric($destinatarios, 'Solicitud de trámite o servicio', $contenido);
    }

    private function notificarCambioEstado(Tramite $tramite): void
    {
        $correo = $tramite->usuario?->correo;

        if (!$correo) {
            return;
        }

        $estado = self::ESTADOS[$tramite->estado] ?? 'actualizado';
        $contenido = "Tu trámite #{$tramite->id} ({$tramite->tipo?->nombre}) ha sido {$estado}."
            . ($tramite->estado === 2 && $tramite->motivo_rechazo ? " Motivo: {$tramite->motivo_rechazo}" : '');

        $this->mailService->sendGeneric($correo, "Trámite o servicio - {$estado}", $contenido);
    }
}

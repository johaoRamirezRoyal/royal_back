<?php

namespace App\Services\tramites;

use App\Enums\Mails;
use App\Models\Tramites\Eps;
use App\Models\Tramites\Tramite;
use App\Models\Tramites\TramiteCorreo;
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
use Illuminate\Support\Facades\Log;
use Throwable;

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
    ) {}

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

            if (! empty($filtros['id_user'])) {
                $query->where('id_user', (int) $filtros['id_user']);
            }

            if (! empty($filtros['tipo_tramite'])) {
                $query->where('tipo_tramite', (int) $filtros['tipo_tramite']);
            }

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
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

            if (! $tramite) {
                return ['error' => true, 'message' => 'Trámite no encontrado', 'status' => 404];
            }

            $tramite->documentos->each(fn ($d) => $d->url_documento = $this->fileStorage->url($d->archivo));

            return ['error' => false, 'message' => 'Trámite obtenido correctamente', 'data' => $tramite];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int, UploadedFile>  $archivos
     * @param  array<int, int>  $grupoFamiliarIds
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

    /** @param UploadedFile[] $archivos */
    public function finalizar(int $id, int $idLog, array $archivos = []): array
    {
        return $this->cambiarEstado($id, 1, $idLog, null, $archivos);
    }

    public function rechazar(int $id, int $idLog, string $motivo): array
    {
        return $this->cambiarEstado($id, 2, $idLog, $motivo);
    }

    /** @param UploadedFile[] $archivos */
    private function cambiarEstado(int $id, int $estado, int $idLog, ?string $motivo, array $archivos = []): array
    {
        try {
            $tramite = Tramite::with('usuario')->find($id);

            if (! $tramite) {
                return ['error' => true, 'message' => 'Trámite no encontrado', 'status' => 404];
            }

            $tramite->update([
                'estado' => $estado,
                'id_edit' => $idLog,
                'fecha_edit' => now(),
                'motivo_rechazo' => $motivo,
            ]);

            $adjuntos = [];
            foreach ($archivos as $archivo) {
                $subido = $this->fileStorage->uploadFile($archivo, 'tramites/documentos');

                // Los discos remotos tienen throw=false: si la subida falló, la ruta viene false/vacía.
                if (empty($subido['ruta'])) {
                    throw new Exception('No se pudo guardar el archivo "'.$archivo->getClientOriginalName().'"');
                }

                TramiteDocumento::create([
                    'id_tramite' => $tramite->id,
                    'archivo' => $subido['ruta'],
                    'id_log' => $idLog,
                ]);
                $adjuntos[] = ['ruta' => $subido['ruta'], 'nombre' => $subido['nombre_original']];
            }

            $correoEnviado = $this->notificarCambioEstado($tramite->fresh(['usuario', 'tipo']), $adjuntos, $idLog);

            return [
                'error' => false,
                'message' => $correoEnviado ? 'Trámite actualizado correctamente' : 'Trámite actualizado, pero el correo de notificación no se pudo enviar',
                'data' => $tramite->fresh(['usuario', 'tipo', 'documentos']),
                'correo_enviado' => $correoEnviado,
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function notificarTramiteCreado(Tramite $tramite, Usuario $solicitante): void
    {
        $tipo = $tramite->tipo?->nombre ?? 'Trámite';
        $nombreCompleto = trim("{$solicitante->nombre} {$solicitante->apellido}");
        $fecha = $tramite->fechareg?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $contenido = "El usuario {$nombreCompleto} ha solicitado un trámite: {$tipo}\n"
            ."Fecha: {$fecha}\n"
            ."Documento: {$solicitante->documento}\n"
            ."Trámite: {$tipo}";

        $destinatarios = array_values(array_unique(array_filter(array_merge(
            Mails::GESTION_HUMANA->recipients(),
            [$solicitante->correo]
        ))));

        $this->mailService->sendGeneric($destinatarios, 'Solicitud de trámite o servicio', $contenido);
    }

    /**
     * Envía el correo de cambio de estado con los archivos como adjuntos reales y deja la bitácora.
     * Nunca lanza: devuelve false si no salió, sin revertir el estado del trámite.
     *
     * Además envía una copia (correo aparte, con aviso) al usuario que gestionó el trámite.
     *
     * @param  array<int, array{ruta: string, nombre: string}>  $adjuntos
     */
    private function notificarCambioEstado(Tramite $tramite, array $adjuntos = [], ?int $idGestor = null): bool
    {
        $solicitante = $tramite->usuario;

        $destinatarios = array_values(array_unique(array_filter(array_merge(
            Mails::GESTION_HUMANA->recipients(),
            [$solicitante->correo]
        ))));

        if (! $destinatarios) {
            return false;
        }

        $nombreCompleto = trim("{$solicitante->nombre} {$solicitante->apellido}");

        $tipo = $tramite->tipo?->nombre ?? 'Trámite';

        $estado = self::ESTADOS[$tramite->estado] ?? 'Actualizado';

        $fechaSolicitud = $tramite->fechareg?->format('d/m/Y H:i') ?? '';

        $fechaEdit = $tramite->fecha_edit?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

        $contenido = "El trámite del usuario {$nombreCompleto} ha sido actualizado: {$tipo}\n"
            ."Fecha de solicitud: {$fechaSolicitud}\n"
            ."Documento: {$solicitante->documento}\n"
            ."Trámite: {$tipo}\n"
            ."Estado: {$estado}\n"
            ."Fecha de actualización: {$fechaEdit}";

        if ($tramite->estado === 2 && $tramite->motivo_rechazo) {
            $contenido .= "\nMotivo del rechazo: {$tramite->motivo_rechazo}";
        }

        if ($adjuntos) {
            $contenido .= "\nDocumentos adjuntos: ".implode(', ', array_column($adjuntos, 'nombre'));
        }

        $asunto = "Trámite o servicio - {$estado}";
        $resultado = $this->enviarConBitacora($tramite, $destinatarios, $asunto, $contenido, $adjuntos);

        try {
            $correoGestor = $idGestor ? Usuario::find($idGestor)?->correo : null;
            if ($correoGestor && ! in_array($correoGestor, $destinatarios, true)) {
                $aviso = "Esta es una copia del correo enviado al solicitante {$nombreCompleto} ({$solicitante->correo}).\n\n";
                $this->enviarConBitacora($tramite, [$correoGestor], "Copia - {$asunto}", $aviso.$contenido, $adjuntos);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar la copia del trámite al gestor', ['id_tramite' => $tramite->id, 'error' => $e->getMessage()]);
        }

        return ! $resultado['error'];
    }

    /** Envía el correo y deja una fila en `tramite_correos` con el resultado. */
    private function enviarConBitacora(Tramite $tramite, array $destinatarios, string $asunto, string $contenido, array $adjuntos): array
    {
        $registro = TramiteCorreo::create([
            'id_tramite' => $tramite->id,
            'destinatarios' => $destinatarios,
            'asunto' => $asunto,
            'adjuntos' => array_column($adjuntos, 'nombre'),
            'estado' => 'pendiente',
            'intentado_at' => now(),
        ]);

        $resultado = $this->mailService->sendGeneric($destinatarios, $asunto, $contenido, null, 'Ver en OMNIA', $adjuntos);

        $registro->update($resultado['error']
            ? ['estado' => 'error', 'error' => $resultado['data']['causa'] ?? $resultado['message']]
            : ['estado' => 'enviado', 'enviado_at' => now()]);

        return $resultado;
    }
}

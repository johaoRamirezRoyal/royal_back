<?php

namespace App\Services\certificados;

use App\Models\Certificados\DocumentoCertificado;
use App\Models\Certificados\SolCertificado;
use App\Models\Usuarios\Usuario;
use App\Services\FileStorageService;
use App\Services\MailService;
use Exception;
use Illuminate\Http\UploadedFile;

class CertificadosServices
{
    /** Catálogo fijo de certificados solicitables — sin tabla propia, solo dos opciones. */
    public const TIPOS = [
        1 => 'Certificación laboral con tiempo de servicio y sueldo que devenga.',
        2 => 'Certificación de ingresos y retenciones año gravable.',
    ];

    public function __construct(
        private FileStorageService $fileStorage,
        private MailService $mailService,
    ) {}

    public function misSolicitudes(int $idUser): array
    {
        try {
            $solicitudes = SolCertificado::with('documento')
                ->where('id_user', $idUser)
                ->orderByDesc('id')
                ->get()
                ->each(fn ($s) => $this->adjuntarUrl($s));

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $solicitudes];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros, int $perPage): array
    {
        try {
            $query = SolCertificado::with(['usuario:id_user,nombre,apellido,documento,correo', 'documento'])
                ->orderByDesc('id');

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
            }

            if (!empty($filtros['tipo_cert'])) {
                $query->where('tipo_cert', (int) $filtros['tipo_cert']);
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
            $paginator->getCollection()->each(fn ($s) => $this->adjuntarUrl($s));

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $data, Usuario $solicitante): array
    {
        try {
            $solicitud = SolCertificado::create([
                'id_user' => $solicitante->id_user,
                'lugar' => $data['lugar'],
                'cargo' => $data['cargo'],
                'nombre_entidad' => $data['nombre_entidad'],
                'trabaja_act' => $data['trabaja_act'],
                'tipo_cert' => $data['tipo_cert'],
                'anio' => $data['anio'] ?? null,
                'estado' => 1,
            ]);

            // Correo fuera de cualquier riesgo de romper la creación: sendGeneric ya
            // atrapa sus propias excepciones y solo loguea si falla (ver MailService).
            $this->notificarSolicitudCreada($solicitud, $solicitante);

            return ['error' => false, 'message' => 'Solicitud de certificado creada correctamente', 'data' => $solicitud];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function subirDocumento(int $idSolicitud, UploadedFile $archivo, int $idLog): array
    {
        try {
            $solicitud = SolCertificado::with('usuario')->find($idSolicitud);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $documentoExistente = DocumentoCertificado::where('id_sol', $idSolicitud)->orderByDesc('id')->first();

            $resultado = $documentoExistente
                ? $this->fileStorage->reemplazar($archivo, $documentoExistente->nombre, 'certificados')
                : $this->fileStorage->uploadFile($archivo, 'certificados');

            if ($documentoExistente) {
                $documentoExistente->update([
                    'nombre' => $resultado['ruta'],
                    'id_log' => $idLog,
                    'anio_mes' => now()->format('Y-m'),
                    'fechareg' => now(),
                ]);
            } else {
                DocumentoCertificado::create([
                    'nombre' => $resultado['ruta'],
                    'id_sol' => $idSolicitud,
                    'id_log' => $idLog,
                    'id_user' => $solicitud->id_user,
                    'anio_mes' => now()->format('Y-m'),
                ]);
            }

            $solicitud->update(['estado' => 2]);

            $this->notificarDocumentoSubido($solicitud);

            $solicitud = $solicitud->fresh(['documento', 'usuario']);
            $this->adjuntarUrl($solicitud);

            return ['error' => false, 'message' => 'Documento subido correctamente', 'data' => $solicitud];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function notificarSolicitudCreada(SolCertificado $solicitud, Usuario $solicitante): void
    {
        $tipo = self::TIPOS[$solicitud->tipo_cert] ?? 'Certificado';
        $nombreCompleto = trim("{$solicitante->nombre} {$solicitante->apellido}");
        $contenido = "Se generó la solicitud de certificado #{$solicitud->id} ({$tipo}) para {$nombreCompleto}.";

        $destinatarios = array_values(array_unique(array_filter(array_merge(
            config('gestionHumana.correo_notificacion', []),
            [$solicitante->correo]
        ))));

        $this->mailService->sendGeneric($destinatarios, 'Solicitud de certificado generada', $contenido);
    }

    private function notificarDocumentoSubido(SolCertificado $solicitud): void
    {
        $correo = $solicitud->usuario?->correo;

        if (!$correo) {
            return;
        }

        $this->mailService->sendGeneric(
            $correo,
            'Tu certificado está listo',
            "Tu solicitud de certificado #{$solicitud->id} ya fue procesada y el documento está disponible para descargar."
        );
    }

    private function adjuntarUrl(SolCertificado $solicitud): void
    {
        if ($solicitud->documento) {
            $solicitud->documento->url_documento = $this->fileStorage->url($solicitud->documento->nombre);
        }
    }
}

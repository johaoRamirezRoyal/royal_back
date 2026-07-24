<?php

namespace App\Services\Admisiones;

use App\Http\Resources\Academico\AnioAcademicoResource;
use App\Models\Admisiones\Aspirante;
use App\Models\Admisiones\CitaPsicologia;
use App\Models\Admisiones\Documento;
use App\Models\Admisiones\Estado;
use App\Models\Admisiones\Familiares;
use App\Models\Admisiones\InformacionMedica;
use App\Models\Admisiones\Inscripcion;
use App\Models\Admisiones\ReferenciasFamiliares;
use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Models\Usuarios\Firma;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use App\Services\NotificacionesService;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmisionesServices extends Service
{
    public function __construct(
        private MailService $mailService,
        private NotificacionesService $notificacionesService
    ) {}

    /**
     * Summary of mailTo
     */
    private array $mailTo = [
        'hernando.ramirez@royalschool.edu.co',
    ];

    /**
     * IDs de perfil de psicóloga por nivel, con los grados que cada una atiende.
     * Se reutiliza para: filtrar aspirantes por psicóloga, resolver a qué
     * psicóloga notificar según el grado del aspirante, y listar psicólogas disponibles.
     */
    private const NIVELES_PSICOLOGAS = [
        21 => ['Stem', 'Pre Kinder', 'Kinder', 'Transición'],
        24 => ['Primero de primaria', 'Segundo de primaria', 'Tercero de primaria', 'Cuarto de primaria', 'Quinto de primaria'],
        25 => ['Sexto de bachillerato', 'Séptimo de bachillerato', 'Octavo de bachillerato', 'Noveno de bachillerato', 'Décimo de bachillerato', 'Undécimo de bachillerato'],
    ];

    private function perfilPsicologaParaGrado(?string $grado): ?int
    {
        foreach (self::NIVELES_PSICOLOGAS as $perfil => $cursos) {
            if (in_array($grado, $cursos, true)) {
                return $perfil;
            }
        }

        return null;
    }

    private function esEstadoRevisionPsicologia(?string $nombreEstado): bool
    {
        $normalizado = mb_strtolower($nombreEstado ?? '');

        return str_contains($normalizado, 'revis') && str_contains($normalizado, 'psicolog');
    }

    /**
     * Duración asumida de una cita de psicología, usada solo para detectar cruces de horario.
     * No hay campo de duración en la tabla; ajustar aquí si cambia la duración real de las citas.
     */
    private const CITA_DURACION_MINUTOS = 45;

    private function existeCruceDeHorario(int $id_psicologa, Carbon $inicio, Carbon $fin, ?int $excluirCitaId = null): bool
    {
        return CitaPsicologia::where('id_psicologa', $id_psicologa)
            ->when($excluirCitaId, fn ($query) => $query->where('id', '!=', $excluirCitaId))
            ->whereBetween('fecha_cita', [
                $inicio->copy()->subMinutes(self::CITA_DURACION_MINUTOS),
                $fin,
            ])
            ->get(['fecha_cita'])
            ->contains(function ($cita) use ($inicio, $fin) {
                $citaInicio = Carbon::parse($cita->fecha_cita);
                $citaFin = $citaInicio->copy()->addMinutes(self::CITA_DURACION_MINUTOS);

                return $citaInicio->lt($fin) && $citaFin->gt($inicio);
            });
    }

    /* =================================================================== INSCRIPCION SERVICES =================================================================== */
    public function registrarInscripcion(array $data): array
    {
        try {
            $unique_code = 'ROYAL-'.strtoupper(uniqid());
            $data['codigo'] = $unique_code;

            Log::info('debug registrarInscripcion', [
                'data' => $data,
            ]);

            $inscripcion = Inscripcion::create($data);

            $inscripcion->refresh();

            $inscripcion->load([
                'anioAcademico',
            ]);

            return [
                'error' => false,
                'message' => 'Inscripción registrada exitosamente.',
                'data' => [
                    'codigo' => $inscripcion->codigo,
                    'estado' => $inscripcion->estado,
                    'id_usuario_registro' => $inscripcion->id_usuario_registro,
                    'fecha_inscripcion' => $inscripcion->fecha_inscripcion,
                    'anio_academico' => $inscripcion->anioAcademico
                        ? new AnioAcademicoResource($inscripcion->anioAcademico)
                        : null,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al registrar la inscripción: '.$e->getMessage(), ['data' => $data]);

            return [
                'error' => true,
                'message' => 'Error al registrar la inscripción: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function actualizarDatosInscripcion(int $id, array $data): array
    {
        try {
            $inscripcion = Inscripcion::find($id);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la inscripción con el ID proporcionado.',
                    'data' => [],
                ];
            }

            $inscripcion->update($data);

            return [
                'error' => false,
                'message' => 'Se ha actualizado la inscripción',
                'data' => $inscripcion->refresh()->toArray(),
            ];

        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la inscripción.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al tratar de actualizar la inscripción.',
                'data' => [],
            ];
        }
    }

    public function eliminarInscripcion(int $id)
    {
        try {
            $inscripcion = Inscripcion::findOrFail($id);
            $inscripcion->delete();

            return [
                'error' => false,
                'message' => 'Inscripción eliminada exitosamente.',
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error al eliminar la inscripción: '.$e->getMessage(), ['id' => $id]);

            return [
                'error' => true,
                'message' => 'Error al eliminar la inscripción: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function eliminarDatoInscripcion(int $id): array
    {
        DB::beginTransaction();

        try {

            $inscripcion = Inscripcion::find($id);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'La inscripción no existe.',
                    'data' => [],
                ];
            }

            // Eliminar relaciones

            ReferenciasFamiliares::where(
                'id_inscripcion',
                $id
            )->delete();

            Aspirante::where(
                'id_inscripcion',
                $id
            )->delete();

            Documento::where(
                'id_inscripcion',
                $id
            )->delete();

            InformacionMedica::where(
                'id_inscripcion',
                $id
            )->delete();

            Familiares::where(
                'id_inscripcion',
                $id
            )->delete();

            DB::commit();

            return [
                'error' => false,
                'message' => 'Inscripción eliminada exitosamente.',
                'data' => [],
            ];
        } catch (Exception $e) {

            DB::rollBack();

            Log::error(
                'Error al eliminar la inscripción: '.$e->getMessage(),
                [
                    'id' => $id,
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );

            return [
                'error' => true,
                'message' => 'Error al eliminar la inscripción.',
                'data' => [],
            ];
        }
    }

    public function mostrarTodasIncripcionesAcudiente(int $id_acudiente): array
    {
        try {
            $inscripciones = Inscripcion::with([
                'anioAcademico',
                'aspirante:id,id_inscripcion,nombre_completo',
                'documento:id,id_inscripcion,nombre_original,url_archivo,id_tipo_documento',
                'estadoInscripcion:id,nombre',
                'documento.tipoDocumento:id,nombre',
            ])->where(['id_usuario_registro' => $id_acudiente])->orderByDesc('fecha_inscripcion')->get();

            Log::info('Info de la inscripcion: ', ['data' => $inscripciones]);

            if ($inscripciones->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna inscripción para ese usuario',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Inscripciones seleccionadas',
                'data' => $inscripciones->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al mostrar inscripciones: ', ['err' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);

            return [
                'error' => true,
                'message' => 'Error en el servidor para encontrar las inscripciones del usuario',
                'data' => [],
            ];
        }
    }

    /**
     * Mostrar la información de la inscripción en base al id
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarInformacionInscripcionId(int $id): array
    {
        try {
            $inscripcion = Inscripcion::with([
                'anioAcademico',
                'aspirante',
                'aspirante.familiares',
            ])->find($id);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la inscripción con el ID proporcionado.',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Información de la inscripción obtenida exitosamente.',
                'data' => $inscripcion->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información de la inscripción: ', ['id' => $id, 'error' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'Error al obtener la información de la inscripción',
                'data' => [],
            ];
        }
    }

    /**
     * Obtener información completa de una inscripción con el codigo, incluyendo el año académico, el aspirante y los familiares del aspirante.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerInformacionCompletaDeInscripcionMedianteCodigo(string $codigo): array
    {
        try {

            $relacionesHistoriaClinica = [
                'cognitivoLenguaje',
                'desarrolloPsicomotor',
                'embarazoParto',
                'estructuraFamiliar',
                'hermanos',
                'historiaEscolar',
                'observacionesFirmas',
                'psicoafectiva',
                'remision',
            ];

            $inscripcion = Inscripcion::with(array_merge([
                'usuarioRegistro:id_user,nombre,apellido',
                'estadoInscripcion:id,nombre',
                'updatedBy:id_user,nombre,apellido',
                'anioAcademico',
                'aspirante',
                'informacionMedica',
                'familiares',
                'referenciaFamiliares',
                'documento',
                'documento.tipoDocumento:id,nombre',
            ], $relacionesHistoriaClinica))
                ->where('codigo', $codigo)
                ->first();

            if ($inscripcion) {
                // Agrupa las relaciones cargadas en un solo atributo "historia_clinica"
                $inscripcion->historia_clinica = collect($relacionesHistoriaClinica)
                    ->mapWithKeys(fn ($rel) => [$rel => $inscripcion->getRelation($rel)]);

                // Oculta esas relaciones del nivel raíz del array/JSON (ya siguen disponibles vía historia_clinica)
                $inscripcion->makeHidden($relacionesHistoriaClinica);
            } else {
                return [
                    'error' => true,
                    'message' => 'No se encontró la inscripción con el código proporcionado.',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Información de la inscripción obtenida exitosamente.',
                'data' => $inscripcion->toArray(),
            ];
            
        } catch (\Throwable $e) {

            Log::error(
                'Error al obtener información de la inscripción',
                [
                    'codigo' => $codigo,
                    'error' => $e->getMessage(),
                ]
            );

            return [
                'error' => true,
                'message' => 'Error al obtener la información de la inscripción.',
                'data' => [],
            ];
        }
    }

    public function actualizarEstadoDeInscripcionAspirante(
        int $id_inscripcion,
        string $estado,
        ?string $correo_acudiente = null,
        ?int $updated_by = null
    ): array {

        try {

            $inscripcion = Inscripcion::with([
                'aspirante',
                'estadoInscripcion',
            ])->find($id_inscripcion);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa inscripción',
                    'data' => [],
                ];
            }

            if ($inscripcion->estado === $estado) {
                return [
                    'error' => false,
                    'message' => 'La inscripción ya tiene ese estado',
                    'data' => $inscripcion->toArray(),
                ];
            }

            $estadoAnterior = $inscripcion->estadoInscripcion?->nombre;

            $inscripcion->update([
                'estado' => $estado,
                'updated_by' => $updated_by,
            ]);

            $inscripcion->refresh()->load('estadoInscripcion');

            $estado_nuevo = $inscripcion->estadoInscripcion?->nombre;

            $mailTo = collect($this->mailTo ?? [])
                ->push($correo_acudiente)
                ->filter()
                ->values()
                ->toArray();

            $titulo = "Actualización de estado de inscripción | {$inscripcion->codigo}";

            $contenido = "
            Hola,

            Se ha actualizado el estado de la inscripción con código {$inscripcion->codigo}.

            Estado anterior: {$estadoAnterior}
            Nuevo estado: {$estado_nuevo}

            Aspirante: ".optional($inscripcion->aspirante)->nombre_completo.'

            Fecha de actualización: '.now()->format('Y-m-d H:i:s');

            if (! empty($mailTo)) {
                $this->mailService->sendGeneric($mailTo, $titulo, $contenido);
            }

            if ($this->esEstadoRevisionPsicologia($estado_nuevo)) {
                $perfilPsicologa = $this->perfilPsicologaParaGrado($inscripcion->aspirante?->grado_aplica);

                $correosPsicologas = $perfilPsicologa
                    ? Usuario::where('perfil', $perfilPsicologa)->pluck('correo')->filter()->toArray()
                    : [];

                if (! empty($correosPsicologas)) {
                    $this->mailService->sendGeneric(
                        $correosPsicologas,
                        "Nueva inscripción en revisión de psicología | {$inscripcion->codigo}",
                        "Se ha asignado la inscripción con código {$inscripcion->codigo} para revisión de psicología.\n\n".
                        'Aspirante: '.optional($inscripcion->aspirante)->nombre_completo."\n".
                        'Grado al que aplica: '.optional($inscripcion->aspirante)->grado_aplica
                    );
                } else {
                    Log::warning('No se encontró psicóloga para notificar el cambio de estado', [
                        'id_inscripcion' => $id_inscripcion,
                        'grado_aplica' => $inscripcion->aspirante?->grado_aplica,
                    ]);
                }
            }

            return [
                'error' => false,
                'message' => 'Estado actualizado correctamente',
                'data' => $inscripcion->fresh()->toArray(),
            ];
        } catch (\Throwable $e) {

            Log::error('Error al actualizar estado de inscripción', [
                'id_inscripcion' => $id_inscripcion,
                'estado' => $estado,
                'correo_acudiente' => $correo_acudiente,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return [
                'error' => true,
                'message' => 'No se pudo actualizar el estado de la inscripción',
                'data' => [],
            ];
        }
    }

    /* =================================================================== ASPIRANTE SERVICES =================================================================== */
    /**
     * Summary of registrarAspirante
     *
     * @return array{data: array, error: bool, message: string|array{data: Aspirante, error: bool, message: string}}
     */
    public function registrarAspirante(array $data): array
    {
        try {

            if (! empty($data['fecha_nacimiento']) && empty($data['edad'])) {
                $data['edad'] = now()->parse($data['fecha_nacimiento'])->age;
            }

            $registroExistente = Aspirante::query()->where('id_inscripcion', $data['id_inscripcion'])->exists();

            if ($registroExistente) {
                return [
                    'error' => true,
                    'message' => 'Esta inscripción ya tiene un aspirante registrado',
                    'data' => $data,
                ];
            }

            $aspirante = Aspirante::create($data);

            return [
                'error' => false,
                'message' => 'Aspirante registrado exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al registrar aspirante: '.$e->getMessage(), ['data' => $data]);

            return [
                'error' => true,
                'message' => 'Error al registrar al aspirante: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Funcionalidad para actualizar la información de un aspirante existente.
     *
     * @return array{data: array, error: bool, message: string|array{data: array, error: bool, message: string}}
     */
    public function actualizarRegistroAspirante(int $id, array $data): array
    {
        try {
            $aspirante = Aspirante::findOrFail($id);

            if (! empty($data['fecha_nacimiento']) && empty($data['edad'])) {
                $data['edad'] = now()->parse($data['fecha_nacimiento'])->age;
            }

            $data['fecha_registro'] = now();

            $aspirante->update($data);

            return [
                'error' => false,
                'message' => 'Aspirante actualizado exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al actualizar aspirante: '.$e->getMessage(), ['id' => $id, 'data' => $data]);

            return [
                'error' => true,
                'message' => 'No se pudo actualizar al aspirante.',
                'data' => [],
            ];
        }
    }

    public function mostrarInformacionAspiranteId(int $id): array
    {
        try {
            $aspirante = Aspirante::findOrFail($id)->with('anioAcademico')->first();

            return [
                'error' => false,
                'message' => 'Información del aspirante obtenida exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información del aspirante: '.$e->getMessage(), ['id' => $id]);

            return [
                'error' => true,
                'message' => 'Error al obtener la información del aspirante: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function eliminarRegistroAspirante(int $id): array
    {
        try {
            $aspirante = Aspirante::findOrFail('id', $id);
            $aspirante->delete();

            return [
                'error' => false,
                'message' => 'Aspirante eliminado exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al eliminar aspirante: '.$e->getMessage(), ['id' => $id]);

            return [
                'error' => true,
                'message' => 'Error al eliminar al aspirante: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function correoInformativoSolicitudInicial(int $id_solicitud, string $correo_acudiente): array
    {
        $informacion = $this->mostrarInformacionAspiranteId($id_solicitud);

        if ($informacion['error'] || empty($informacion['data'])) {
            Log::error('No se pudo obtener la información del aspirante para enviar el correo', [
                'id_solicitud' => $id_solicitud,
                'detalle' => $informacion['message'] ?? 'Data vacía',
            ]);

            return [
                'error' => true,
                'message' => 'No se pudo obtener la información para el correo.',
                'data' => [],
            ];
        }

        $aspirante = $informacion['data'];
        try {
            $titulo = 'Notificación | Registro de Aspirante Actualizado';

            $anioEscolar = 'N/A';

            if (! empty($aspirante['anio_academico'])) {
                $aa = $aspirante['anio_academico'];

                if (is_array($aa)) {
                    // Verificamos que existan las llaves antes de concatenar
                    $inicio = $aa['anio_inicio'] ?? '';
                    $fin = $aa['anio_fin'] ?? '';
                    $anioEscolar = trim("$inicio - $fin", ' -');
                } elseif (is_object($aa)) {
                    $anioEscolar = "{$aa->anio_inicio} - {$aa->anio_fin}";
                } elseif (is_string($aa)) {
                    $anioEscolar = $aa;
                }
            }

            $viveCon = $aspirante['vive_con'] ?? 'No especificado';

            $contenido = "Se ha procesado la información del aspirante ID #{$aspirante['id']}:\n\n";
            $contenido .= "• Nombre completo: {$aspirante['nombre_completo']}\n";
            $contenido .= "• Grado al que aplica: {$aspirante['grado_aplica']}\n";
            $contenido .= "• Año Académico: {$anioEscolar}\n";
            $contenido .= '• Edad: '.($aspirante['edad'] ?? 'N/A')." años\n";
            $contenido .= '• Sexo: '.($aspirante['sexo'] ?? 'N/A')."\n";

            $contenido .= "\nDetalles de Convivencia:\n";
            $contenido .= "• Vive con: {$viveCon}\n";
            $contenido .= '• Lugar de nacimiento: '.($aspirante['lugar_nacimiento'] ?? 'N/A')."\n";

            if (! empty($aspirante['tiene_hermanos_colegio'])) {
                $contenido .= "• Hermanos en el colegio: Sí\n";
                $contenido .= '• Detalle hermanos: '.($aspirante['info_hermanos_colegio'] ?? 'N/A')."\n";
            } else {
                $contenido .= "• Hermanos en el colegio: No\n";
            }

            $contenido .= "\nInformación Adicional:\n";
            $contenido .= '• Antecedentes: '.($aspirante['antecedentes_escolares'] ?? 'Ninguno')."\n";
            $contenido .= '• Religión: '.($aspirante['religion'] ?? 'N/A')."\n";

            // CORRECCIÓN: Parse de fecha con Carbon
            $fechaReg = ! empty($aspirante['fecha_registro'])
                ? Carbon::parse($aspirante['fecha_registro'])->format('d/m/Y H:i')
                : now()->format('d/m/Y H:i');

            $contenido .= "\n---\nFecha de registro: ".$fechaReg;

            $this->mailService->sendGeneric($correo_acudiente, $titulo, $contenido);

            return [
                'error' => false,
                'message' => 'Correo informativo enviado exitosamente.',
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error al enviar correo informativo: '.$e->getMessage(), [
                'id_solicitud' => $id_solicitud,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => true,
                'message' => 'Error al enviar correo: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /* =================================================================== FAMILIARES SERVICES =================================================================== */

    /**
     * Summary of agregarFamiliarAspirante
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function agregarFamiliarAspirante(array $data): array
    {
        try {
            $familiares = [];

            foreach ($data['familiares'] as $familiarData) {
                $familiares[] = Familiares::create($familiarData);
            }

            return [
                'error' => false,
                'message' => 'Familiar agregado exitosamente.',
                'data' => $familiares,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'error en familiar agregar');

            return [
                'error' => true,
                'message' => 'No se pudo agregar al familiar del aspirante, intente nuevamente.',
                'data' => [],
            ];
        }
    }

    /**
     * Función para actualizar la información, recibe un array que debe haber pasado por la Request.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarFamiliarAspirante(int $id_familiar, array $data): array
    {
        try {
            if (! $id_familiar) {
                return [
                    'error' => true,
                    'message' => 'El ID del familiar es obligatorio para la actualización.',
                    'data' => [],
                ];
            }

            $payload = isset($data['familiares']) && is_array($data['familiares'])
            ? $data['familiares'][0]
            : $data;

            unset($payload['id'], $payload['id_inscripcion']);

            $familiar = Familiares::findOrFail($id_familiar);
            $familiar->update($payload);

            if (! $familiar->wasChanged()) {
                return [
                    'error' => true,
                    'message' => 'No se realizaron cambios en la información del familiar.',
                    'data' => $familiar->toArray(),
                ];
            }

            return [
                'error' => false,
                'message' => 'Familiar actualizado exitosamente.',
                'data' => $familiar->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al actualizar al familiar del aspirante: ', ['error' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'No se pudo actualizar al familiar del aspirante, comuniquese con soporte.',
                'data' => [],
            ];
        }
    }

    /**
     * Eliminar al familiar del aspirante por su ID.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function eliminarFamiliarAspirante(int $id_familiar): array
    {
        try {
            $familiar = Familiares::find($id_familiar);

            if (! $familiar) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el familiar con el ID proporcionado.',
                    'data' => [],
                ];
            }

            $eliminado = $familiar->delete();

            if (! $eliminado) {
                return [
                    'error' => true,
                    'message' => 'No se pudo eliminar al familiar, intente nuevamente.',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Familiar eliminado exitosamente.',
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error al eliminar al familiar del aspirante: ', ['error' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'No se puede eliminar al familiar del aspirante, comuniquese con soporte.',
                'data' => [],
            ];
        }
    }

    // ========================================= INFORMACIÓN MEDICA SERVICES =========================================
    /**
     * Crear o agregar uno o varios registros de información médica de un aspirante
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function agregarInformacionMedicaAspirante(array $data): array
    {
        try {
            if (empty($data['informacion_medica']) || ! is_array($data['informacion_medica'])) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos un registro de información médica.',
                    'data' => [],
                ];
            }

            $registros = [];

            foreach ($data['informacion_medica'] as $informacion) {
                $registros[] = InformacionMedica::create($informacion)->toArray();
            }

            return [
                'error' => false,
                'message' => 'Información medica guardada',
                'data' => $registros,
            ];
        } catch (Exception $e) {
            Log::error('Error creando la información medica: ', ['err' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'Error en el servidor creando la información medica',
                'data' => $data,
            ];
        }
    }

    /**
     * Actualizar la información del aspirante, con el id y la información en un array
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarInformacionMedicaAspirante(int $id_informacion, array $data): array
    {
        try {
            $informacionMedica = InformacionMedica::find($id_informacion);

            if (! $informacionMedica) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa información',
                    'data' => $informacionMedica->toArray(),
                ];
            }

            $informacionMedica->update($data);

            return [
                'error' => false,
                'message' => 'Información médica actualizada correctamente',
                'data' => $informacionMedica->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(
                'No se pudo actualizar la información médica',
                [
                    'id_informacion' => $id_informacion,
                    'error' => $e->getMessage(),
                ]
            );

            return [
                'error' => true,
                'message' => 'Error en el servidor, no se pudo guardar la información médica.',
                'data' => $data,
            ];
        }
    }

    /**
     * eliminar información medica con el id de la información medica
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function eliminarInformacionMedicaAspirante(int $id_informacion): array
    {
        try {
            $informacion = InformacionMedica::find($id_informacion);

            if (! $informacion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la información a eliminar',
                    'data' => $informacion->toArray(),
                ];
            }

            $dataEliminada = $informacion->toArray();

            $informacion->delete();

            return [
                'error' => false,
                'message' => 'Se eliminó completamente la información médica',
                'data' => $dataEliminada,
            ];
        } catch (Exception $e) {
            Log::error('Error al eliminar la información medica: ', ['err' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la información medica',
                'data' => [],
            ];
        }
    }

    // ========================================= SOLICITUD DE DOCUMENTOS SERVICES =========================================
    /**
     * Subir documentos necesarios para la inscripción
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function subirDocumentoInscripcion(int $id_inscripcion, array $data): array
    {
        try {
            $documento = Documento::create([...$data, 'id_inscripcion' => $id_inscripcion]);

            return [
                'error' => false,
                'message' => 'Documento guardado correctamente',
                'data' => $documento->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error añadiendo el archivo: ', ['err' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'No se pudo añadir el archivo',
                'data' => $data,
            ];
        }
    }

    public function verInfoDocumentos(array $ids)
    {
        try {
            $eliminados = Documento::whereIn('id', $ids)->get();

            if ($eliminados->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No existen documentos con esos IDS',
                    'data' => $ids,
                ];
            }

            return [
                'error' => false,
                'message' => 'Se eliminaron todos los documentos',
                'data' => $eliminados->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'ERror al borrar los documentos de la base de datos');

            return [
                'error' => true,
                'message' => 'Error inesperado',
                'data' => [],
            ];
        }
    }

    /**
     * Función para eliminar varios documentos a la vez
     *
     * @return array{data: array, error: bool, message: string|array{data: bool|int|mixed|null, error: bool, message: string}}
     */
    public function eliminarDocumentos(array $ids)
    {
        try {
            $eliminados = Documento::whereIn('id', $ids)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No existen documentos con esos IDS',
                    'data' => $ids,
                ];
            }

            return [
                'error' => false,
                'message' => 'Se eliminaron todos los documentos',
                'data' => $eliminados,
            ];

        } catch (Exception $e) {
            $this->sendError($e, 'ERror al borrar los documentos de la base de datos');

            return [
                'error' => true,
                'message' => 'Error inesperado',
                'data' => [],
            ];
        }
    }

    // ========================================= SOLICITUD DE REFERENCIAS FAMILIARES SERVICES =========================================
    /**
     * Subir referencias familiares a una inscripción.
     * Acepta tanto un único registro (campos en la raíz) como un batch (clave "referencias" con el array).
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function subirReferenciasFamiliaresAspirante(int $id_inscripcion, array $data): array
    {
        try {
            $inscripcion = Inscripcion::find($id_inscripcion);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'La inscripción no existe',
                    'data' => [],
                ];
            }

            $referenciasInput = (array_key_exists('referencias', $data) && is_array($data['referencias']) && ! empty($data['referencias']))
                ? $data['referencias']
                : [$data];

            $creadas = [];
            foreach ($referenciasInput as $ref) {
                $creadas[] = ReferenciasFamiliares::create([...$ref, 'id_inscripcion' => $id_inscripcion])->toArray();
            }

            $cantidad = count($creadas);
            $message = $cantidad === 1
                ? 'Referencia familiar agregada'
                : "Se agregaron {$cantidad} referencias familiares";

            return [
                'error' => false,
                'message' => $message,
                'data' => $creadas,
            ];
        } catch (Exception $e) {
            Log::error('Error al subir referencias familiares: ', [
                'err' => $e->getMessage(),
                'data' => $data,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return [
                'error' => true,
                'message' => 'No se pudieron agregar las referencias familiares',
                'data' => [],
            ];
        }
    }

    /**
     * Actualizar referencia familiar existente
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarReferenciasFamiliaresAspirante(int $id_referencia_familiar, array $data): array
    {
        try {
            $referencia = ReferenciasFamiliares::find($id_referencia_familiar);
            if (! $referencia) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa referencia',
                    'data' => [],
                ];
            }

            $referencia->update($data);

            return [
                'error' => false,
                'message' => 'Referencia actualizada',
                'data' => $referencia->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Error al actualizar la referencia: ', [
                'err' => $e->getMessage(),
                'data' => [
                    ...$data,
                    'id_referencia_familiar' => $id_referencia_familiar,
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ],
            ]);

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la referencia',
                'data' => [],
            ];
        }
    }

    /**
     * Metodo para eliminar una referencia familiar con el id de dicha referencia
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function eliminarReferenciaFamiliarAspirante(int $id_referencia_familiar): array
    {
        try {
            $referencia = ReferenciasFamiliares::find($id_referencia_familiar);
            $referencia_data = $referencia->toArray();
            if (! $referencia) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa referencia a eliminar!',
                    'data' => [],
                ];
            }

            $borrado = $referencia->delete();
            if (! $borrado) {
                return [
                    'error' => true,
                    'message' => 'No se borró la referencia... ',
                    'data' => $referencia_data,
                ];
            }

            return [
                'error' => false,
                'message' => 'Referencia eliminada correctamente',
                'data' => $referencia->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(
                'Error al eliminar la referencia: ',
                ['id' => $id_referencia_familiar, 'err' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]
            );

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la referencia',
                'data' => [],
            ];
        }
    }

    // Metodo para validar si el correo de un acudiente se encuentra registrado
    // y cuenta con al menos UNA isncripcion

    public function hasRegistration(string $email)
    {
        try {
            return Usuario::query()
                ->where('correo', $email)
                ->whereHas('admisiones_inscripciones')
                ->exists();
        } catch (Exception $e) {
            Log::error('Error añadiendo el archivo: ', ['err' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'Ha ocurrido un error en la consulta de datos',
                'data' => $e->getMessage(),
            ];
        }
    }

    // ========================================= ESTADO DE INSCRIPCIONES SERVICES =========================================

    /**
     * Metodo para listar los estados de las inscripciones, se puede añadir el parametro $activo para filtrar por estado.
     *
     * @param  mixed  $estado
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarTodosLosEstadosDeInscripcion(?bool $activo = null): array
    {
        try {
            $estado = Estado::query();

            if (! is_null($activo)) {
                $estado->where('estado', $activo);
            }

            $estados = $estado->get();

            if ($estados->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ningún estado para las inscripciones',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Estados de inscripciones obtenidos correctamente',
                'data' => $estados->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los datos de los estados de las inscripciones');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los estados de las inscripciones',
                'data' => [],
            ];
        }
    }

    /**
     * Metodo para mostrar un estado de inscripción.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarEstadoDeInscripcionId(int $id): array
    {
        try {
            $estado = Estado::find($id);

            if (! $estado) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el estado de inscripción con el ID proporcionado',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Estado de inscripción obtenido correctamente',
                'data' => $estado->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el estado de inscripción por ID');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el estado de inscripción.',
                'data' => [],
            ];
        }
    }

    /**
     * Summary of cambiarEstadoDeEstadoInscripcion
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function cambiarEstadoDeEstadoInscripcion(int $id, bool $estado): array
    {
        try {
            $estado = Estado::find($id);
            if (! $estado) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el estado de inscripción con el ID proporcionado.',
                    'data' => [],
                ];
            }

            if ($estado->estado === $estado) {
                return [
                    'error' => true,
                    'message' => 'El estado de inscripción ya se encuentra en ese estado.',
                    'data' => $estado->toArray(),
                ];
            }

            $estado_actualizado = $estado->update(['estado' => $estado]);
            if (! $estado_actualizado) {
                return [
                    'error' => true,
                    'message' => 'No se pudo actualizar el estado de inscripción, intente nuevamente.',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Estado de inscripción actualizado correctamente',
                'data' => $estado->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado del estado de inscripción');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el estado de inscripción.',
                'data' => [],
            ];
        }
    }

    /**
     * Método para filtrar las inscripciones en base al curso.
     *
     * @param  string  $perfil_psicologa
     * @return array{data: array, error: bool, message: string}
     */
    public function mostrarAspirantesAPsicologa(int $perfil_psicologa): array
    {

        $cursosPermitidos = self::NIVELES_PSICOLOGAS[$perfil_psicologa] ?? [];

        try {
            $inscripciones = Inscripcion::with([
                'usuarioRegistro:id_user,nombre,apellido',
                'estadoInscripcion:id,nombre',
                'updatedBy:id_user,nombre,apellido',
                'anioAcademico',
                'aspirante',
                'informacionMedica',
                'familiares',
                'referenciaFamiliares',
                'documento',
                'documento.tipoDocumento:id,nombre',
            ])->whereHas('aspirante', function ($query) use ($cursosPermitidos) {
                $query->whereIn('grado_aplica', $cursosPermitidos);
            })->get();

            if ($inscripciones->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna inscripción para ese usuario',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Inscripciones seleccionadas',
                'data' => $inscripciones->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las inscripciones de un psicologa');

            return [
                'error' => true,
                'message' => 'Error al obtener las inscripciones de un psicologa',
                'data' => [],
            ];
        }
    }

    /**
     * Listar las psicólogas disponibles (usuarios con un perfil de psicóloga y estado activo).
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function listarPsicologasDisponibles(): array
    {
        try {
            $psicologas = Usuario::whereIn('perfil', array_keys(self::NIVELES_PSICOLOGAS))
                ->where('estado', 'activo')
                ->get(['id_user', 'nombre', 'apellido', 'correo', 'perfil']);

            if ($psicologas->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron psicólogas disponibles',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Psicólogas disponibles obtenidas correctamente',
                'data' => $psicologas->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las psicólogas disponibles');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las psicólogas disponibles',
                'data' => [],
            ];
        }
    }

    /**
     * Agendar una cita de psicología para una inscripción. Notifica al acudiente que registró la inscripción.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function agendarCitaPsicologia(int $id_inscripcion, int $id_psicologa, string $fecha_cita, ?string $observaciones = null): array
    {
        try {
            $inscripcion = Inscripcion::with(['usuarioRegistro', 'aspirante'])->find($id_inscripcion);

            if (! $inscripcion) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa inscripción',
                    'data' => [],
                ];
            }

            $psicologa = Usuario::whereIn('perfil', array_keys(self::NIVELES_PSICOLOGAS))->find($id_psicologa);

            if (! $psicologa) {
                return [
                    'error' => true,
                    'message' => 'El usuario indicado no es una psicóloga válida',
                    'data' => [],
                ];
            }

            $inicio = Carbon::parse($fecha_cita);
            $fin = $inicio->copy()->addMinutes(self::CITA_DURACION_MINUTOS);

            if ($this->existeCruceDeHorario($id_psicologa, $inicio, $fin)) {
                return [
                    'error' => true,
                    'message' => 'La psicóloga ya tiene una cita agendada en ese horario',
                    'data' => [],
                ];
            }

            $cita = CitaPsicologia::create([
                'id_inscripcion' => $id_inscripcion,
                'id_psicologa' => $id_psicologa,
                'fecha_cita' => $fecha_cita,
                'observaciones' => $observaciones,
            ]);

            $acudiente = $inscripcion->usuarioRegistro;

            if ($acudiente) {
                $this->notificacionesService->crear(
                    $acudiente->id_user,
                    'Cita de psicología agendada',
                    'Se ha agendado una cita de psicología para la inscripción '.$inscripcion->codigo.
                    ' el '.Carbon::parse($fecha_cita)->format('d/m/Y H:i').
                    '. Aspirante: '.optional($inscripcion->aspirante)->nombre_completo.
                    '. Psicóloga: '.$psicologa->nombre.' '.$psicologa->apellido.'.',
                    $acudiente->perfil ?? 1
                );
            }

            $this->notificacionesService->crear(
                $psicologa->id_user,
                'Nueva cita de psicología a tu cargo',
                'Se te ha asignado una cita de psicología para la inscripción '.$inscripcion->codigo.
                ' el '.Carbon::parse($fecha_cita)->format('d/m/Y H:i').
                '. Aspirante: '.optional($inscripcion->aspirante)->nombre_completo.'.',
                $psicologa->perfil ?? 1
            );

            return [
                'error' => false,
                'message' => 'Cita de psicología agendada correctamente',
                'data' => $cita->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al agendar la cita de psicología');

            return [
                'error' => true,
                'message' => 'Error en el servidor al agendar la cita de psicología',
                'data' => [],
            ];
        }
    }

    /**
     * Consultar si una inscripción ya tiene cita(s) de psicología agendada(s).
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerCitasPsicologiaDeInscripcion(int $id_inscripcion): array
    {
        try {
            $citas = CitaPsicologia::with('psicologa:id_user,nombre,apellido,correo')
                ->where('id_inscripcion', $id_inscripcion)
                ->orderByDesc('fecha_cita')
                ->get();

            return [
                'error' => false,
                'message' => $citas->isEmpty() ? 'La inscripción no tiene citas de psicología agendadas' : 'Citas de psicología encontradas',
                'data' => [
                    'tiene_cita' => $citas->isNotEmpty(),
                    'citas' => $citas->toArray(),
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al consultar la cita de psicología de la inscripción');

            return [
                'error' => true,
                'message' => 'Error en el servidor al consultar la cita de psicología',
                'data' => [],
            ];
        }
    }

    /**
     * Reprogramar (cambiar fecha/hora) una cita de psicología existente. Notifica al acudiente.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarFechaCitaPsicologia(int $id_cita, string $fecha_cita): array
    {
        try {
            $cita = CitaPsicologia::with(['inscripcion.usuarioRegistro', 'psicologa'])->find($id_cita);

            if (! $cita) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa cita de psicología',
                    'data' => [],
                ];
            }

            $inicio = Carbon::parse($fecha_cita);
            $fin = $inicio->copy()->addMinutes(self::CITA_DURACION_MINUTOS);

            if ($this->existeCruceDeHorario($cita->id_psicologa, $inicio, $fin, $cita->id)) {
                return [
                    'error' => true,
                    'message' => 'La psicóloga ya tiene una cita agendada en ese horario',
                    'data' => [],
                ];
            }

            $cita->update(['fecha_cita' => $fecha_cita]);

            $acudiente = $cita->inscripcion?->usuarioRegistro;

            if ($acudiente) {
                $mensajeReprogramacion = 'La cita de psicología para la inscripción '.$cita->inscripcion->codigo.
                    ' fue reprogramada para el '.$inicio->format('d/m/Y H:i').
                    '. Psicóloga: '.$cita->psicologa->nombre.' '.$cita->psicologa->apellido.'.';

                $this->notificacionesService->crear(
                    $acudiente->id_user,
                    'Cita de psicología reprogramada',
                    $mensajeReprogramacion,
                    $acudiente->perfil ?? 1
                );

                if ($acudiente->correo) {
                    $this->mailService->sendGeneric(
                        $acudiente->correo,
                        "Cita de psicología reprogramada | {$cita->inscripcion->codigo}",
                        $mensajeReprogramacion
                    );
                }
            }

            return [
                'error' => false,
                'message' => 'Fecha de la cita actualizada correctamente',
                'data' => $cita->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la fecha de la cita de psicología');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la fecha de la cita',
                'data' => [],
            ];
        }
    }

    /**
     * Reasignar la psicóloga a cargo de una cita existente. Notifica y envía correo
     * al acudiente y a la nueva psicóloga informando el cambio.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarPsicologaCitaPsicologia(int $id_cita, int $id_psicologa): array
    {
        try {
            $cita = CitaPsicologia::with(['inscripcion.usuarioRegistro', 'psicologa'])->find($id_cita);

            if (! $cita) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa cita de psicología',
                    'data' => [],
                ];
            }

            if ($cita->id_psicologa === $id_psicologa) {
                return [
                    'error' => false,
                    'message' => 'La cita ya está asignada a esa psicóloga',
                    'data' => $cita->toArray(),
                ];
            }

            $psicologaNueva = Usuario::whereIn('perfil', array_keys(self::NIVELES_PSICOLOGAS))->find($id_psicologa);

            if (! $psicologaNueva) {
                return [
                    'error' => true,
                    'message' => 'El usuario indicado no es una psicóloga válida',
                    'data' => [],
                ];
            }

            $inicio = Carbon::parse($cita->fecha_cita);
            $fin = $inicio->copy()->addMinutes(self::CITA_DURACION_MINUTOS);

            if ($this->existeCruceDeHorario($id_psicologa, $inicio, $fin, $cita->id)) {
                return [
                    'error' => true,
                    'message' => 'La psicóloga ya tiene una cita agendada en ese horario',
                    'data' => [],
                ];
            }

            $psicologaAnterior = $cita->psicologa;

            $cita->update(['id_psicologa' => $id_psicologa]);

            $mensajeCambio = 'La psicóloga a cargo de la cita de psicología para la inscripción '.$cita->inscripcion->codigo.
                ' del '.$inicio->format('d/m/Y H:i').
                ' cambió de '.optional($psicologaAnterior)->nombre.' '.optional($psicologaAnterior)->apellido.
                ' a '.$psicologaNueva->nombre.' '.$psicologaNueva->apellido.'.';

            $acudiente = $cita->inscripcion?->usuarioRegistro;

            if ($acudiente) {
                $this->notificacionesService->crear(
                    $acudiente->id_user,
                    'Cambio de psicóloga en cita de psicología',
                    $mensajeCambio,
                    $acudiente->perfil ?? 1
                );
            }

            if ($psicologaAnterior) {
                $this->notificacionesService->crear(
                    $psicologaAnterior->id_user,
                    'Se te retiró una cita de psicología',
                    'Ya no estás a cargo de la cita de psicología para la inscripción '.$cita->inscripcion->codigo.
                    ' del '.$inicio->format('d/m/Y H:i').
                    '. Fue reasignada a '.$psicologaNueva->nombre.' '.$psicologaNueva->apellido.'.',
                    $psicologaAnterior->perfil ?? 1
                );
            }

            $this->notificacionesService->crear(
                $psicologaNueva->id_user,
                'Nueva cita de psicología a tu cargo',
                'Se te ha asignado la cita de psicología para la inscripción '.$cita->inscripcion->codigo.
                ' del '.$inicio->format('d/m/Y H:i').'.',
                $psicologaNueva->perfil ?? 1
            );

            $correosDestino = array_filter([$acudiente->correo ?? null, $psicologaNueva->correo]);

            if (! empty($correosDestino)) {
                $this->mailService->sendGeneric(
                    $correosDestino,
                    "Cambio de psicóloga en cita de psicología | {$cita->inscripcion->codigo}",
                    $mensajeCambio
                );
            }

            return [
                'error' => false,
                'message' => 'Psicóloga de la cita actualizada correctamente',
                'data' => $cita->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la psicóloga de la cita de psicología');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la psicóloga de la cita',
                'data' => [],
            ];
        }
    }

    /**
     * Actualizar el estado (AGENDADA | ATENDIDA) de una cita de psicología.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function actualizarEstadoCitaPsicologia(int $id_cita, string $estado_cita): array
    {
        try {
            $cita = CitaPsicologia::with(['inscripcion.usuarioRegistro', 'psicologa'])->find($id_cita);

            if (! $cita) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa cita de psicología',
                    'data' => [],
                ];
            }

            $cita->update(['estado_cita' => $estado_cita]);

            if ($estado_cita === 'ATENDIDA') {
                $firmaPsicologa = Firma::where('id_user', $cita->id_psicologa)->where('activo', 1)->first();

                if ($firmaPsicologa) {
                    HceObservacionesFirmas::updateOrCreate(
                        ['id_inscripcion' => $cita->id_inscripcion],
                        ['id_firma_psicologa' => $firmaPsicologa->id]
                    );
                }
            }

            $acudiente = $cita->inscripcion?->usuarioRegistro;

            if ($acudiente) {
                $estadoLegible = $estado_cita === 'ATENDIDA' ? 'marcada como atendida' : 'reprogramada como agendada';

                $mensajeEstado = 'La cita de psicología para la inscripción '.$cita->inscripcion->codigo.
                    ' del '.Carbon::parse($cita->fecha_cita)->format('d/m/Y H:i').
                    ' fue '.$estadoLegible.'. Psicóloga: '.optional($cita->psicologa)->nombre.' '.optional($cita->psicologa)->apellido.'.';

                $this->notificacionesService->crear(
                    $acudiente->id_user,
                    'Estado de cita de psicología actualizado',
                    $mensajeEstado,
                    $acudiente->perfil ?? 1
                );

                if ($acudiente->correo) {
                    $this->mailService->sendGeneric(
                        $acudiente->correo,
                        "Estado de cita de psicología actualizado | {$cita->inscripcion->codigo}",
                        $mensajeEstado
                    );
                }
            }

            return [
                'error' => false,
                'message' => 'Estado de la cita actualizado correctamente',
                'data' => $cita->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el estado de la cita de psicología');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el estado de la cita',
                'data' => [],
            ];
        }
    }

    /**
     * Guarda la URL del documento de observación (ya subido a Cloudinary) en la cita.
     */
    public function subirDocumentoObservacionCita(int $id_cita, string $url_documento): array
    {
        try {
            $cita = CitaPsicologia::find($id_cita);

            if (! $cita) {
                return [
                    'error' => true,
                    'message' => 'No se encontró esa cita de psicología',
                    'data' => [],
                ];
            }

            $cita->update(['doc_observacion' => $url_documento]);

            return [
                'error' => false,
                'message' => 'Documento de observación guardado correctamente',
                'data' => $cita->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el documento de observación de la cita');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el documento de observación',
                'data' => [],
            ];
        }
    }

    /**
     * Listar citas de psicología. Sin filtros trae todas; se puede acotar por psicóloga y/o rango de fechas.
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function listarCitasPsicologia(?int $id_psicologa = null, ?string $fecha_desde = null, ?string $fecha_hasta = null): array
    {
        try {
            $citas = CitaPsicologia::with([
                'psicologa:id_user,nombre,apellido,correo,perfil',
                'inscripcion:id,codigo,id_usuario_registro',
                'inscripcion.aspirante:id,id_inscripcion,nombre_completo,grado_aplica',
            ])
                ->when($id_psicologa, fn ($query) => $query->where('id_psicologa', $id_psicologa))
                ->when($fecha_desde, fn ($query) => $query->where('fecha_cita', '>=', $fecha_desde))
                ->when($fecha_hasta, fn ($query) => $query->where('fecha_cita', '<=', $fecha_hasta))
                ->orderBy('fecha_cita')
                ->get();

            return [
                'error' => false,
                'message' => $citas->isEmpty() ? 'No se encontraron citas de psicología' : 'Citas de psicología obtenidas correctamente',
                'data' => $citas->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar las citas de psicología');

            return [
                'error' => true,
                'message' => 'Error en el servidor al listar las citas de psicología',
                'data' => [],
            ];
        }
    }
}

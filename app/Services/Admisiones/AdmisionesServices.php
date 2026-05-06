<?php

namespace App\Services\Admisiones;

use App\Mail\GenericMail;
use App\Models\Admisiones\Aspirante;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdmisionesServices
{
    /**
     * Summary of mailTo
     * @var array
     */
    private array $mailTo = [
        'hernando.ramirez@royalschool.edu.co'
    ];

    /**
     * Summary of registrarAspirante
     * @param array $data
     * @return array{data: array, error: bool, message: string|array{data: Aspirante, error: bool, message: string}}
     */
    public function registrarAspirante(array $data): array{
        try {

            if(!empty($data['fecha_nacimiento']) && empty($data['edad'])){
                $data['edad'] = now()->parse($data['fecha_nacimiento'])->age;
            }

            $aspirante = Aspirante::create($data);

            return [
                'error' => false,
                'message' => 'Aspirante registrado exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        }catch(\Exception $e){
            Log::error('Error al registrar aspirante: ' . $e->getMessage(), ['data' => $data]);
            return [
                'error' => true,
                'message' => 'Error al registrar al aspirante: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Funcionalidad para actualizar la información de un aspirante existente.
     * @param int $id
     * @param array $data
     * @return array{data: array, error: bool, message: string|array{data: array, error: bool, message: string}}
     */
    public function actualizarRegistroAspirante(int $id, array $data): array{
        try{
            $aspirante = Aspirante::findOrFail($id);

            if(!empty($data['fecha_nacimiento']) && empty($data['edad'])){
                $data['edad'] = now()->parse($data['fecha_nacimiento'])->age;
            }

            $aspirante->update($data);

            return [
                'error' => false,
                'message' => 'Aspirante actualizado exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        }catch(\Exception $e){
            Log::error('Error al actualizar aspirante: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);
            return[
                'error' => true,
                'message' => 'No se pudo actualizar al aspirante.',
                'data' => []
            ];
        }
    }

    public function mostrarInformacionAspiranteId(int $id): array{
        try {
            $aspirante = Aspirante::findOrFail($id)->with('anioAcademico')->get();
            return [
                'error' => false,
                'message' => 'Información del aspirante obtenida exitosamente.',
                'data' => $aspirante->toArray(),
            ];
        }catch(\Exception $e){
            Log::error('Error al obtener información del aspirante: ' . $e->getMessage(), ['id' => $id]);
            return [
                'error' => true,
                'message' => 'Error al obtener la información del aspirante: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function eliminarRegistroAspirante(int $id): array {
        try{
            $aspirante = Aspirante::findOrFail($id);
            $aspirante->delete();

            return [ 
                'error' => false,
                'message' => 'Aspirante eliminado exitosamente.',
                'data' => $aspirante->toArray()
            ];
        }catch(\Exception $e){
            Log::error('Error al eliminar aspirante: ' . $e->getMessage(), ['id' => $id]);
            return [
                'error' => true,
                'message' => 'Error al eliminar al aspirante: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function correoInformativoSolicitudInicial(int $id_solicitud, string $correo_acudiente): array
    {
        $informacion = $this->mostrarInformacionAspiranteId($id_solicitud);

        if ($informacion['error'] || empty($informacion['data'])) {
            Log::error('No se pudo obtener la información del aspirante para enviar el correo', [
                'id_solicitud' => $id_solicitud,
                'detalle'      => $informacion['message'] ?? 'Data vacía'
            ]);
            return [
                'error'   => true,
                'message' => 'No se pudo obtener la información para el correo.',
                'data'    => []
            ];
        }

        $aspirante = $informacion['data'][0];
        try {
            $titulo = "Notificación | Registro de Aspirante Actualizado";

            $anioEscolar = "N/A";

            if (!empty($aspirante['anio_academico'])) {
                $aa = $aspirante['anio_academico'];

                if (is_array($aa)) {
                    // Verificamos que existan las llaves antes de concatenar
                    $inicio = $aa['anio_inicio'] ?? '';
                    $fin = $aa['anio_fin'] ?? '';
                    $anioEscolar = trim("$inicio - $fin", " -");
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
            $contenido .= "• Edad: " . ($aspirante['edad'] ?? 'N/A') . " años\n";
            $contenido .= "• Sexo: " . ($aspirante['sexo'] ?? 'N/A') . "\n";

            $contenido .= "\nDetalles de Convivencia:\n";
            $contenido .= "• Vive con: {$viveCon}\n";
            $contenido .= "• Lugar de nacimiento: " . ($aspirante['lugar_nacimiento'] ?? 'N/A') . "\n";

            if (!empty($aspirante['tiene_hermanos_colegio'])) {
                $contenido .= "• Hermanos en el colegio: Sí\n";
                $contenido .= "• Detalle hermanos: " . ($aspirante['info_hermanos_colegio'] ?? 'N/A') . "\n";
            } else {
                $contenido .= "• Hermanos en el colegio: No\n";
            }

            $contenido .= "\nInformación Adicional:\n";
            $contenido .= "• Antecedentes: " . ($aspirante['antecedentes_escolares'] ?? 'Ninguno') . "\n";
            $contenido .= "• Religión: " . ($aspirante['religion'] ?? 'N/A') . "\n";

            // CORRECCIÓN: Parse de fecha con Carbon
            $fechaReg = !empty($aspirante['fecha_registro'])
                ? \Carbon\Carbon::parse($aspirante['fecha_registro'])->format('d/m/Y H:i')
                : now()->format('d/m/Y H:i');

            $contenido .= "\n---\nFecha de registro: " . $fechaReg;

            $this->mailTo = [$correo_acudiente];

            // Envío del correo
            Mail::to($this->mailTo)
                ->send(new GenericMail($titulo, $contenido));

            return [
                'error'   => false,
                'message' => 'Correo informativo enviado exitosamente.',
                'data'    => []
            ];
        } catch (\Exception $e) {
            Log::error('Error al enviar correo informativo: ' . $e->getMessage(), [
                'id_solicitud' => $id_solicitud,
                'trace'        => $e->getTraceAsString()
            ]);
            return [
                'error'   => true,
                'message' => 'Error al enviar correo: ' . $e->getMessage(),
                'data'    => []
            ];
        }
    }
}
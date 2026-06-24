<?php
namespace App\Services\LlegadasTardeEstudiantes;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\LlegadasTarde\LlegadasTarde as ModelsLlegadasTarde;
use App\Models\Usuarios\Usuario;
use App\Services\Service;
use Exception;

class LlegadasTarde extends Service
{
    private array $mailTo = [
        'hernando.ramirez@royalschool.edu.co'
    ];

    public function agregarLlegadaTarde(int $id_alumno, string $fecha, string $hora): array{
        try {
            $yaRegistrada = ModelsLlegadasTarde::where('id_alumno', $id_alumno)
                ->where('fecha', $fecha)
                ->exists();

            if ($yaRegistrada) {
                return [
                    'error' => false,
                    'message' => 'El alumno ya tiene una llegada tarde registrada hoy',
                    'data' => []
                ];
            }

            //Encontramos el ultimo periodo academico agregado y activo
            $periodo_academico = PeriodoAcademico::where('activo', true)
                ->orderByDesc('fecha_inicio')
                ->first();

            $data = [
                'id_alumno' => $id_alumno,
                'fecha' => $fecha,
                'hora' => $hora,
            ];

            if(!$periodo_academico){
                return [
                    'error' => true,
                    'message' => "No se encontró un periodo académico disponible para registrar la llegada tarde",
                    'data' => []
                ];
            }

            $data['id_periodo_academico'] = $periodo_academico->id;
            
            $llegadaTarde = ModelsLlegadasTarde::create($data);

            if(!$llegadaTarde){
                return [
                    'error' => true,
                    'message' => "No se ha podido crear la llegada tarde",
                    'data' => $data
                ];
            }

            return [
                'error' => false,
                'message' => "Llegada tarde creada correctamente",
                'data' => $llegadaTarde->toArray()
            ];

        }catch(Exception $e){
            $this->sendError($e, "Error al agregar la llegada tarde");
            return [
                'error' => true,
                'message' => "Error al agregar la llegada tarde",
                'data' => []
            ];
        }
    }
}
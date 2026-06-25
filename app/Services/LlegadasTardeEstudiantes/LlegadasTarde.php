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

    public function agregarLlegadaTarde(int $id_alumno, string $fecha, string $hora): array
    {
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

            if (!$periodo_academico) {
                return [
                    'error' => true,
                    'message' => "No se encontró un periodo académico disponible para registrar la llegada tarde",
                    'data' => []
                ];
            }

            $data['id_periodo_academico'] = $periodo_academico->id;

            $llegadaTarde = ModelsLlegadasTarde::create($data);

            if (!$llegadaTarde) {
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
        } catch (Exception $e) {
            $this->sendError($e, "Error al agregar la llegada tarde");
            return [
                'error' => true,
                'message' => "Error al agregar la llegada tarde",
                'data' => []
            ];
        }
    }

    public function obtenerLlegadasTarde(?int $id_periodo_academico = null, ?int $id_alumno = null): array
    {
        try {
            if ($id_periodo_academico === null) {

                $periodo = PeriodoAcademico::where('activo', 1)
                    ->latest('id')
                    ->first();

                if (!$periodo) {
                    return [
                        'error' => true,
                        'message' => 'No existe un período académico activo',
                        'data' => []
                    ];
                }

                $id_periodo_academico = $periodo->id;
            }

            $llegadas_tarde = ModelsLlegadasTarde::where('id_periodo_academico', $id_periodo_academico)
                ->with('alumno:id_user,nombre,apellido,correo')
                ->with('periodoAcademico:id,fecha_inicio,fecha_fin,activo')
                ->when($id_alumno !== null, function ($query) use ($id_alumno) {
                    $query->where('id_alumno', $id_alumno);
                })
                ->get();

            if ($llegadas_tarde->isEmpty()) {
                return [
                    'error' => false,
                    'message' => 'No se encontraron llegadas tarde para el periodo académico',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Obtenidos los llegadas tarde',
                'data' => [
                    'total_llegadas_tarde' => $llegadas_tarde->count(),
                    'registros' => $llegadas_tarde
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener las llegadas tarde");
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener las llegadas tarde",
                'data' => []
            ];
        }
    }

    public function eliminarLlegadaTarde(array $ids_llegadas_tarde): array
    {
        try {
            $registros = ModelsLlegadasTarde::whereIn('id', $ids_llegadas_tarde)->get();

            if ($registros->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron llegadas tarde para los IDs: ' . implode(', ', $ids_llegadas_tarde),
                    'data' => []
                ];
            }

            $eliminados = ModelsLlegadasTarde::whereIn('id', $ids_llegadas_tarde)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se eliminaron los registros de las llegadas tarde',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha eliminado esta llegada tarde",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al eliminar la llegada tarde");

            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de eliminar la llegada tarde",
                'data' => []
            ];
        }
    }
}

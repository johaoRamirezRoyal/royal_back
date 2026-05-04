<?php

namespace App\Services\Admisiones;
use App\Models\Admisiones\Aspirante;
use Illuminate\Support\Facades\Log;

class AdmisionesServices
{
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
                'data' => $aspirante,
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

    public function mostrarInformacionAspiranteId(int $id): array{
        try {
            $aspirante = Aspirante::findOrFail($id);
            return [
                'error' => false,
                'message' => 'Información del aspirante obtenida exitosamente.',
                'data' => $aspirante,
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
                'data' => $aspirante
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
}
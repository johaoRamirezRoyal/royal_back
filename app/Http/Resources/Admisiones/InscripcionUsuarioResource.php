<?php

namespace App\Http\Resources\Admisiones;

use App\Http\Resources\Academico\AnioAcademicoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscripcionUsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'estado' => $this->estado,

            'anio_academico' => new AnioAcademicoResource(
                $this->whenLoaded('anioAcademico')
            ),

            'fecha_inscripcion' => $this->fecha_inscripcion,
            'fecha_actualizacion' => $this->fecha_actualizacion,
        ];
    }
}
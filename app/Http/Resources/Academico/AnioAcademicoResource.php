<?php

namespace App\Http\Resources\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnioAcademicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_inicio' => $this->anio_inicio,
            'anio_fin' => $this->anio_fin,
            'activo' => $this->activo,
        ];
    }
}

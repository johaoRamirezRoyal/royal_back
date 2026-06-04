<?php

namespace App\Http\Resources\Usuarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admisiones\InscripcionUsuarioResource;

class UsuarioInscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_user,
            'documento' => $this->documento,

            'nombre_completo' => trim(
                "{$this->nombre} {$this->apellido}"
            ),

            'correo' => $this->correo,
            'telefono' => $this->telefono,

            'estado' => $this->estado,

            'cantidad_inscripciones' => $this->whenLoaded(
                'inscripciones',
                fn () => $this->inscripciones->count()
            ),

            'inscripciones' => InscripcionUsuarioResource::collection(
                $this->whenLoaded('inscripciones')
            ),
        ];
    }
}
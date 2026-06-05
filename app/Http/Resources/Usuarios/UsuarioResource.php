<?php

namespace App\Http\Resources\Usuarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_user,
            'perfil' => $this->perfil,
            'nombre_perfil' => $this->whenLoaded('perfilRelacion', fn () => $this->perfilRelacion->nombre),
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'estado' => $this->estado,
            'id_nivel' => $this->id_nivel,
            'nombre_nivel' => $this->whenLoaded('nivelRelacion', fn () => $this->nivelRelacion->nombre),
            'foto_carnet' => $this->foto_carnet,
            'correo' => $this->correo,
        ];
    }
}

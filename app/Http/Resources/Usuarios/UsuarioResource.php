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
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo
        ];
    }
}

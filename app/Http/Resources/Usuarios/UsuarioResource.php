<?php

namespace App\Http\Resources\Usuarios;

use App\Services\branding\MarcaDominioService;
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
        $marca = app(MarcaDominioService::class)->resolverPorCorreo($this->correo);

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
            // Logo y nombre de la marca del dominio de este correo (multi-tenant) — logo_path
            // ya es una URL de Cloudinary lista para usar en un <img src>, no una ruta
            // relativa. Ambos null si no hay marca configurada/activa para el dominio (el
            // frontend cae al logo/título genérico de OMNIA ahí).
            'logo_path' => $marca['url'],
            'nombre_marca' => $marca['nombre'],
        ];
    }
}

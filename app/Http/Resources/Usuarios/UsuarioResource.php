<?php

namespace App\Http\Resources\Usuarios;

use App\Services\branding\MarcaDominioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\AdminManagement\BasesDatosService;

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
            // Nombre de la foto subida en Perfil (en el disco de uploads, se sirve por
            // /api/public/{nombre}?w=); null si no tiene. Viaja con la sesión para que el
            // frontend no pida /info-perfil solo para mostrar el avatar.
            'foto_perfil' => $this->fotoPerfil()->where('activo', 1)->orderByDesc('id')->value('nombre_foto'),
            'correo' => $this->correo,
            // Logo, nombre y color de la marca del dominio de este correo (multi-tenant) —
            // logo_path ya es una URL de Cloudinary lista para usar en un <img src>, no una
            // ruta relativa. Los tres null si no hay marca configurada/activa para el
            // dominio (el frontend cae al logo/título/color genérico de OMNIA ahí, ver
            // useAppLogo.hook.ts / useMarcaColor.hook.ts).
            'logo_path' => $marca['url'],
            'nombre_marca' => $marca['nombre'],
            'color_marca' => $marca['color'],
            // Carpeta raíz del colegio en Cloudinary ("Bases de datos"): el frontend arma con
            // ella las URLs de los archivos (src/utils/fileUrl.ts).
            'carpeta_archivos' => BasesDatosService::carpeta(config('database.default')),
        ];
    }
}

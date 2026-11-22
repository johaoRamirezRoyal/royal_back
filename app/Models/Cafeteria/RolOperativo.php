<?php

namespace App\Models\Cafeteria;

use Illuminate\Database\Eloquent\Model;

/** Función de trabajo en la cafetería. No da permisos de la API (eso es cron_opciones). */
class RolOperativo extends Model
{
    protected $table = 'caf_roles_operativos';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}

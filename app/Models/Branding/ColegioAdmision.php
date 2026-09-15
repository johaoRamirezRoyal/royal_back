<?php

namespace App\Models\Branding;

use Illuminate\Database\Eloquent\Model;

class ColegioAdmision extends Model
{
    /** Vive en `admin_management`, no en la base operativa (ver config/database.php). */
    protected $connection = 'admin_management';

    protected $table = 'colegios_admision';

    protected $primaryKey = 'id';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = 'fecha_updated';

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
        'color',
        'logo_path',
        'logo_public_id',
        'logo_secundario_path',
        'logo_secundario_public_id',
        'connection',
        'tipo_calendario',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $attributes = [
        'activo' => true,
        'tipo_calendario' => 'B',
    ];
}

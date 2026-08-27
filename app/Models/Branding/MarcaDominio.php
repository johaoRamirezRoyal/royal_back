<?php

namespace App\Models\Branding;

use Illuminate\Database\Eloquent\Model;

class MarcaDominio extends Model
{
    /** Vive en `admin_management`, no en la base operativa (ver config/database.php). */
    protected $connection = 'admin_management';

    protected $table = 'marcas_dominio';

    protected $primaryKey = 'id';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = 'fecha_updated';

    protected $fillable = [
        'dominio',
        'nombre',
        'logo_path',
        'logo_public_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $attributes = [
        'activo' => true,
    ];
}

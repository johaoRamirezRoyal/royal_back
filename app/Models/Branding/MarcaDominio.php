<?php

namespace App\Models\Branding;

use Illuminate\Database\Eloquent\Model;

/**
 * Dominio de correo → marca (`marcas`). Las columnas de identidad que aún tiene la tabla
 * (nombre, logo_path, color...) son las de antes de `marcas`: ya no se leen ni se escriben
 * y se borran en una migración aparte.
 */
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
        'id_marca',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'id_marca' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca');
    }
}

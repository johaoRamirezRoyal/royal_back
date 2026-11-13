<?php

namespace App\Models\Branding;

use Illuminate\Database\Eloquent\Model;

/**
 * Colegio por slug de URL (`/{slug}/admissions`, `/{slug}/padres`): a qué base apunta y su
 * marca (`marcas`). Las columnas de identidad que aún tiene la tabla (nombre, logo_path,
 * color...) son las de antes de `marcas`: ya no se leen ni se escriben y se borran en una
 * migración aparte.
 */
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
        'connection',
        'id_marca',
        'tipo_calendario',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'id_marca' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'tipo_calendario' => 'B',
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca');
    }
}

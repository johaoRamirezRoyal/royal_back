<?php

namespace App\Models\Branding;

use Illuminate\Database\Eloquent\Model;

/**
 * Identidad de una institución (nombre, lema, color, logos, imagen de fondo). La usan los
 * dominios de correo (`marcas_dominio`: sesión, PDFs, Excel) y los colegios por slug
 * (`colegios_admision`: Admisiones, portal para padres). Vive en `admin_management`.
 */
class Marca extends Model
{
    protected $connection = 'admin_management';

    protected $table = 'marcas';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = 'fecha_updated';

    protected $fillable = [
        'nombre',
        'descripcion',
        'color',
        'logo_path',
        'logo_public_id',
        'logo_secundario_path',
        'logo_secundario_public_id',
        'imagen_fondo_path',
        'imagen_fondo_public_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    public function dominios()
    {
        return $this->hasMany(MarcaDominio::class, 'id_marca');
    }

    public function colegios()
    {
        return $this->hasMany(ColegioAdmision::class, 'id_marca');
    }
}

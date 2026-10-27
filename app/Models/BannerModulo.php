<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Aviso de un solo módulo del sidebar — ver la migración create_banner_modulo_table. */
class BannerModulo extends Model
{
    // Transversal, igual que BannerInformativo (ver su comentario).
    protected $connection = 'admin_management';

    protected $table = 'banner_modulo';

    public $timestamps = false;

    protected $fillable = [
        'modulo',
        'dominio',
        'mensaje',
        'imagen',
        'imagen_public_id',
        'variante',
        'tamano',
        'activo',
        'expira_en',
        'mostrar_modal',
        'actualizado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'expira_en' => 'datetime',
        'mostrar_modal' => 'boolean',
    ];
}

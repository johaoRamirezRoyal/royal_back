<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerInformativo extends Model
{
    public const ID_CONFIG = 1;

    public const VARIANTES = ['info', 'warning', 'success', 'danger'];

    public const TAMANOS = ['sm', 'md', 'lg', 'xl'];

    /** Usado por BannerInformativoService::enviarCorreo cuando `destinatario_correo` queda
     * vacío — alias de distribución del lado del proveedor de correo, no una lista de
     * usuarios individuales (ver migración add_correo_modal_to_banner_informativo_table). */
    public const DESTINATARIO_CORREO_DEFAULT = 'all@royalschool.edu.co';

    /** Transversal en `admin_management` (ver config/database.php), igual que MarcaDominio/
     * LlaveMaestra/LogDominio — sin esto, sigue la connection activa del Super Admin
     * (SwitchActiveConnection) o del tenant de turno, y falla en cualquiera que no sea la
     * que tiene la tabla. */
    protected $connection = 'admin_management';

    protected $table = 'banner_informativo';

    public $timestamps = false;

    protected $fillable = [
        'mensaje',
        'dominio',
        'variante',
        'tamano',
        'activo',
        'expira_en',
        'enviar_correo',
        'destinatario_correo',
        'mostrar_modal',
        'actualizado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'expira_en' => 'datetime',
        'enviar_correo' => 'boolean',
        'mostrar_modal' => 'boolean',
    ];

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }
}

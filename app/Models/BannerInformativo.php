<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerInformativo extends Model
{
    public const ID_CONFIG = 1;

    public const VARIANTES = ['info', 'warning', 'success', 'danger'];

    public const TAMANOS = ['sm', 'md', 'lg', 'xl'];

    /** Formatos del archivo del banner: imagen, GIF o video corto (MP4/WebM). */
    public const MEDIA = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm'];

    /** Transversal en `admin_management` (ver config/database.php), igual que MarcaDominio/
     * LlaveMaestra/LogDominio — sin esto, sigue la connection activa del Super Admin
     * (SwitchActiveConnection) o del tenant de turno, y falla en cualquiera que no sea la
     * que tiene la tabla. */
    protected $connection = 'admin_management';

    protected $table = 'banner_informativo';

    public $timestamps = false;

    protected $fillable = [
        'mensaje',
        'imagen',
        'imagen_public_id',
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

    /** Tipo de recurso de Cloudinary de un archivo de banner (`imagen` guarda imagen, GIF o
     * video): hace falta para borrarlo, Cloudinary no encuentra un video borrado como imagen. */
    public static function tipoRecurso(?string $url): string
    {
        return $url && str_contains($url, '/video/upload/') ? 'video' : 'image';
    }

    public static function actual(): self
    {
        return self::findOrFail(self::ID_CONFIG);
    }
}

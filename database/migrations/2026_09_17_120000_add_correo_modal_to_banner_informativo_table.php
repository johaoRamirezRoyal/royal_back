<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío del banner por correo (`enviar_correo` + `destinatario_correo`, un solo
 * destinatario — pensado para un alias de distribución del lado del proveedor de correo,
 * ej. "all@royalschool.edu.co", no una lista de usuarios individuales — ver
 * BannerInformativoService::enviarCorreo, que reusa MailService::sendGeneric con ese único
 * destinatario, sin el loop por-usuario que causó el incidente de rate-limit de Noticias)
 * y modal automático (`mostrar_modal`) — ver BannerInformativoController::obtener (público)
 * y el frontend InformativeBannerModal.
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->boolean('enviar_correo')->default(false)->after('expira_en');
            $table->string('destinatario_correo', 190)->nullable()->after('enviar_correo');
            $table->boolean('mostrar_modal')->default(false)->after('destinatario_correo');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn(['enviar_correo', 'destinatario_correo', 'mostrar_modal']);
        });
    }
};

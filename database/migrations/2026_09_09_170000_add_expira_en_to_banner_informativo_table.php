<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expiración opcional del banner informativo — null = sin fecha límite (se apaga solo con
 * el toggle `activo`). Con fecha, `BannerInformativoController::obtener` (público) deja de
 * mostrarlo apenas se cumple, sin esperar a que nadie lo desactive a mano; `activo` en sí
 * no cambia (el admin sigue viendo el banner como "activado" en el panel, solo que ya
 * venció — igual que un permiso expirado que no se revoca, se deja envejecer).
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->timestamp('expira_en')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn('expira_en');
        });
    }
};

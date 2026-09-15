<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'admin_management';

    /**
     * Logotipo secundario opcional (ej. sello de acreditación, red/alianza a la que
     * pertenece el colegio) — se muestra en la esquina superior derecha de la página
     * pública de admisiones, aparte del logo principal (splash del DecoPanel). A
     * diferencia del principal, no tiene genérico de OMNIA de respaldo: si no se sube
     * ninguno, simplemente no se muestra nada ahí.
     */
    public function up(): void
    {
        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->string('logo_secundario_path', 255)->nullable()->after('logo_public_id');
            $table->string('logo_secundario_public_id', 255)->nullable()->after('logo_secundario_path');
        });
    }

    public function down(): void
    {
        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->dropColumn(['logo_secundario_path', 'logo_secundario_public_id']);
        });
    }
};

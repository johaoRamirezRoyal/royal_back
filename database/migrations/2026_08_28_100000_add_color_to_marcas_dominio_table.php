<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vive en la base `admin_management` (ver config/database.php), igual que la tabla. */
    protected $connection = 'admin_management';

    /**
     * Color identificativo de la marca (hex, ej. "#0b1f5e") — se usa en el frontend como
     * acento de UI (encabezados de tabla, hover, selección) para que la app se sienta propia
     * de cada institución, no solo el logo. Null = sin marca definida, el frontend cae al
     * color por defecto (--brand-primary en index.css, el navy de Royal) — ver
     * MarcaDominioService::resolverPorCorreo y useMarcaColor.hook.ts.
     */
    public function up(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};

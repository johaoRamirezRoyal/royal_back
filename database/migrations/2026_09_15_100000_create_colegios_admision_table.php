<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vive en `admin_management` (ver config/database.php), no en la operativa — igual
     * que marcas_dominio, es transversal a los tenants, no dato de uno en particular. */
    protected $connection = 'admin_management';

    /**
     * Mapea el `slug` de un colegio en la URL pública de admisiones
     * (`/{slug}/admissions`, ver ResolveColegioAdmision) a la connection de base de datos
     * de ese colegio (BasesDatosService::CONNECTIONS) y a su marca (nombre/color/logo) —
     * mismo espíritu que `marcas_dominio` (MarcaDominioService), pero resuelto por
     * segmento de URL en vez de por dominio de correo, y con el dato adicional de a qué
     * base de datos debe apuntar la petición.
     *
     * `color`/`logo_path` son nullable a propósito, a diferencia de `marcas_dominio`
     * (donde el logo es obligatorio al crear la marca): un colegio nuevo necesita existir
     * en esta tabla para que el ruteo de base de datos funcione desde el día uno, aunque
     * todavía no se le haya subido un logo real — sin marca resuelta, el frontend cae al
     * logo/color genérico de OMNIA (ver DecoPanel.ui.tsx).
     */
    public function up(): void
    {
        Schema::create('colegios_admision', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('slug', 190)->unique();
            $table->string('nombre', 190);
            $table->string('descripcion', 190)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('logo_public_id', 255)->nullable();
            $table->string('connection', 100);
            $table->boolean('activo')->default(true);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colegios_admision');
    }
};

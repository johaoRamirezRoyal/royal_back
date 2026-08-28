<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vive en la base `admin_management` (ver config/database.php), no en la operativa —
     * es transversal a los dominios/tenants, no dato de uno en particular. */
    protected $connection = 'admin_management';

    /**
     * Mapea un dominio de correo (ej. "royalschool.edu.co") al logo de esa institución —
     * usado para que el logo mostrado en la app (sidebar/header/favicon) y en los
     * documentos generados (paz y salvo, horario) se adapte según el dominio del correo
     * del usuario, en vez de mostrar siempre el mismo logo hardcodeado. Un dominio sin fila
     * acá (o con activo=false) cae al logo genérico de OMNIA. — ver
     * MarcaDominioService::resolverPorCorreo.
     *
     * El logo se sube a Cloudinary (mismo storage que firmas/documentos de admisiones,
     * ver CloudinaryService) en vez de al disco local: `logo_path` guarda la URL pública
     * ya lista para consumir (frontend/documentos), `logo_public_id` el identificador que
     * exige `CloudinaryService::deleteFile()` al reemplazar o eliminar una marca.
     */
    public function up(): void
    {
        Schema::create('marcas_dominio', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('dominio', 190)->unique();
            $table->string('nombre', 190)->nullable();
            $table->string('logo_path', 255);
            $table->string('logo_public_id', 255);
            $table->boolean('activo')->default(true);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas_dominio');
    }
};

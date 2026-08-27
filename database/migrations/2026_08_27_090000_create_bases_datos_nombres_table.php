<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre visible (editable desde el panel) para cada connection listada en
 * BasesDatosService::CONNECTIONS — vive en `admin_management` igual que
 * marcas_dominio/logs_dominio (transversal, no de un tenant en particular). Sin fila acá =
 * se usa el label por defecto de CONNECTIONS.
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('bases_datos_nombres', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('connection', 60)->unique();
            $table->string('nombre', 190);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bases_datos_nombres');
    }
};

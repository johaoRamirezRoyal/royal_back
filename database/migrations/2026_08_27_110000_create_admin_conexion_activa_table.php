<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia por Super Admin (dominio de administración) de qué connection usar como
 * `database.default` para el resto de la app durante su sesión — ver
 * App\Http\Middleware\SwitchActiveConnection. Es infraestructura para el multi-tenant a
 * futuro (una base operativa por colegio/dominio): hoy solo existen `mysql` y
 * `admin_management`, y esta última no tiene las tablas de negocio (usuarios, inventario,
 * académico, ...), así que elegirla rompe la mayoría de los módulos hasta que existan
 * bases operativas reales por tenant.
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('admin_conexion_activa', function (Blueprint $table) {
            $table->integer('id_user')->primary();
            $table->string('connection', 60);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_conexion_activa');
    }
};

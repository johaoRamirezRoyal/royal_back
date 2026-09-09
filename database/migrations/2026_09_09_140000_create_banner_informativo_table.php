<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fila única (id=1) con el banner informativo mostrado en el login y en el sistema
 * general ya autenticado — mismo patrón de singleton que `configuracion_instituciones`,
 * pero transversal (vive en `admin_management`, ver config/database.php, igual que
 * MarcaDominio/LlaveMaestra/LogDominio): el banner debe verse igual sin importar en qué
 * connection esté un Super Admin (ver SwitchActiveConnection) o a qué tenant pertenezca
 * el visitante — sin `$connection` explícito acá, el modelo sigue la connection activa de
 * turno y el banner desaparece/falla en cuanto esa connection no sea la que tiene la
 * tabla (bug real, visto en producción: "Table 'sami_hebreo.banner_informativo' doesn't
 * exist" al consultar con la connection activa puesta en otro tenant).
 * Se siembra desactivado para no mostrar nada hasta que un Super Admin lo configure.
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('banner_informativo', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->text('mensaje')->nullable();
            $table->string('variante', 20)->default('info');
            $table->boolean('activo')->default(false);
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        // DB::table() (a diferencia de Schema::) NO seguiría solo el $connection de la
        // migración — el facade resuelve contra config('database.default') si no se le
        // dice explícitamente, así que hay que nombrarla acá también.
        DB::connection('admin_management')->table('banner_informativo')->insert([
            'id' => 1,
            'mensaje' => null,
            'variante' => 'info',
            'activo' => false,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_informativo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 1: funciones de trabajo (roles operativos) y quién trabaja en cada
 * cafetería. Un rol operativo **no da permisos de la API**: eso sigue en
 * cron_opciones/cron_permisos. Una persona puede estar en varias cafeterías y con varias
 * funciones; cada asignación tiene vigencia y estado (App\Enums\Cafeteria\EstadoAsignacion).
 */
return new class extends Migration
{
    private const ROLES = [
        ['COORDINADOR', 'Coordinador', 'Coordina la operación de la cafetería.'],
        ['CAJERO', 'Cajero', 'Atiende el punto de venta y cobra.'],
        ['ALMACENISTA', 'Almacenista', 'Recibe mercancía y controla el inventario.'],
        ['COCINA', 'Cocina', 'Prepara los productos.'],
    ];

    public function up(): void
    {
        Schema::create('caf_roles_operativos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('caf_asignaciones_personal', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_cafeteria')->constrained('caf_cafeterias')->restrictOnDelete();
            $table->integer('id_user')->index();
            $table->foreignId('id_rol_operativo')->constrained('caf_roles_operativos')->restrictOnDelete();
            $table->integer('asignado_por');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('ACTIVA');
            $table->timestamps();

            $table->index(['id_cafeteria', 'id_user', 'estado']);
        });

        $ahora = now();
        DB::table('caf_roles_operativos')->insert(array_map(fn (array $r) => [
            'codigo' => $r[0], 'nombre' => $r[1], 'descripcion' => $r[2], 'activo' => true,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ], self::ROLES));
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_asignaciones_personal');
        Schema::dropIfExists('caf_roles_operativos');
    }
};

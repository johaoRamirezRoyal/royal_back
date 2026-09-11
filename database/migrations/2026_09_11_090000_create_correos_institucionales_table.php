<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correos institucionales agrupados por `grupo` (coincide con el nombre del case en
     * App\Enums\Mails, ej. "GESTION_HUMANA") — reemplaza los arrays hardcodeados que
     * antes vivían directamente en el enum. Un mismo grupo puede tener varias filas
     * (varios destinatarios); `activo=false` lo excluye de Mails::recipients() sin
     * borrar la fila.
     */
    public function up(): void
    {
        Schema::create('correos_institucionales', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('grupo', 100)->index();
            $table->string('nombre', 190)->nullable();
            $table->string('correo', 190);
            $table->boolean('activo')->default(true);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('correos_institucionales')->insert([
            ['grupo' => 'ADMISIONES', 'nombre' => null, 'correo' => 'admissions@royalschool.edu.co'],
            ['grupo' => 'BIBLIOTECA', 'nombre' => null, 'correo' => 'library@royalschool.edu.co'],
            ['grupo' => 'GESTION_HUMANA', 'nombre' => null, 'correo' => 'gestionhumana@royalschool.edu.co'],
            ['grupo' => 'GESTION_HUMANA', 'nombre' => null, 'correo' => 'gestor.administrativo@royalschool.edu.co'],
            ['grupo' => 'GESTION_HUMANA', 'nombre' => 'Steycy Morales', 'correo' => 'steycy.morales@royalschool.edu.co'],
            ['grupo' => 'DIRECCION_ADMINISTRATIVA', 'nombre' => null, 'correo' => 'direccionadministrativa@royalschool.edu.co'],
            ['grupo' => 'DIRECCION_ADMINISTRATIVA', 'nombre' => 'Carolina Otalora', 'correo' => 'carolina.otalora@royalschool.edu.co'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_institucionales');
    }
};

<?php

use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice en `usuarios.correo`: el login busca por correo en cada base de colegio y la tabla
 * no tenía índice (lectura completa en cada login). Crece con los acudientes del portal para
 * padres. No es único: el mismo correo personal puede repetirse. `usuarios` es legacy (sin
 * migración propia): solo se le agrega el índice, en todas las bases con usuarios.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->connectionsDisponibles() as $connection) {
            if (! $this->tieneIndice($connection)) {
                Schema::connection($connection)->table('usuarios', fn ($table) => $table->index('correo', 'usuarios_correo_index'));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->connectionsDisponibles() as $connection) {
            if ($this->tieneIndice($connection)) {
                Schema::connection($connection)->table('usuarios', fn ($table) => $table->dropIndex('usuarios_correo_index'));
            }
        }
    }

    /**
     * Connections cuya base existe. En local no siempre están todas las bases de colegio
     * (p. ej. `sami_hebreo`): esas se saltan con un aviso. Cualquier otro error detiene la
     * migración, para que en producción no quede una base sin índice sin que se note.
     *
     * @return list<string>
     */
    private function connectionsDisponibles(): array
    {
        $disponibles = [];
        foreach (BasesDatosService::connectionsConUsuarios() as $connection) {
            try {
                DB::connection($connection)->getPdo();
                $disponibles[] = $connection;
            } catch (QueryException|PDOException $e) {
                if (! str_contains($e->getMessage(), '[1049]')) {
                    throw $e;
                }
                fwrite(STDERR, "  Se omite la connection '{$connection}': su base de datos no existe.\n");
            }
        }

        return $disponibles;
    }

    private function tieneIndice(string $connection): bool
    {
        return DB::connection($connection)->select("SHOW INDEX FROM usuarios WHERE Key_name = 'usuarios_correo_index'") !== [];
    }
};

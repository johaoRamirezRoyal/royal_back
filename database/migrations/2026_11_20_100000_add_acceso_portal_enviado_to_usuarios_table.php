<?php

use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `usuarios.acceso_portal_enviado_at`: cuándo se le envió a un acudiente el correo de acceso al
 * portal para padres (AcudientesService::enviarAccesoPortal). El módulo Acudientes lo muestra y
 * filtra. `usuarios` es legacy: solo se agrega la columna, en todas las bases con usuarios.
 *
 * Rellena los envíos ya hechos con el enlace que sigue en `password_reset_tokens`: el de acceso
 * dura 7 días y el de recuperar contraseña 60 minutos, así que uno de más de un día es de acceso.
 * Si después se pidió recuperar la contraseña, ese envío ya no se ve (el enlace se reemplaza).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->connectionsDisponibles() as $connection) {
            if (! Schema::connection($connection)->hasColumn('usuarios', 'acceso_portal_enviado_at')) {
                Schema::connection($connection)->table('usuarios', fn ($table) => $table->dateTime('acceso_portal_enviado_at')->nullable());
            }

            DB::connection($connection)->update(
                "UPDATE usuarios u
                 JOIN password_reset_tokens t ON t.email = u.correo
                 SET u.acceso_portal_enviado_at = t.created_at
                 WHERE u.acceso_portal_enviado_at IS NULL
                   AND t.created_at IS NOT NULL
                   AND TIMESTAMPDIFF(HOUR, t.created_at, t.expires_at) > 24
                   AND u.perfil IN (SELECT id_perfil FROM perfiles WHERE tipo = 'ACUDIENTE')"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->connectionsDisponibles() as $connection) {
            if (Schema::connection($connection)->hasColumn('usuarios', 'acceso_portal_enviado_at')) {
                Schema::connection($connection)->table('usuarios', fn ($table) => $table->dropColumn('acceso_portal_enviado_at'));
            }
        }
    }

    /**
     * Connections cuya base existe (en local no siempre están todas): las que no, se saltan con
     * un aviso. Cualquier otro error detiene la migración. Igual que
     * 2026_11_13_100000_add_index_correo_to_usuarios_table.
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
};

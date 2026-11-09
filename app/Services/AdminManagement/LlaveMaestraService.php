<?php

namespace App\Services\AdminManagement;

use App\Models\LlaveMaestra;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Llaves de acceso de soporte de un solo uso — ver comentario de la migración
 * create_llaves_maestras_table para el porqué de la connection `admin_management` y la
 * ausencia de FK. `generar()` es la única vez que la llave cruda existe en texto plano;
 * de ahí en adelante solo su hash vive en BD (mismo principio que
 * AuthServices::registrarDispositivoConfiable).
 */
class LlaveMaestraService
{
    private const MINUTOS_VIGENCIA = 10;

    /** Lo devuelve redimir() cuando la cuenta destino ya tiene una sesión abierta por llave. */
    public const SESION_ABIERTA = 'sesion_abierta';

    /**
     * @return array{key: string, expira_en: \Illuminate\Support\Carbon}|null null si el
     * usuario destino no existe o no está activo.
     */
    public function generar(int $idUserObjetivo, string $connectionObjetivo, int $generadoPor): ?array
    {
        $usuario = Usuario::on($connectionObjetivo)->find($idUserObjetivo);

        if (!$usuario || $usuario->estado !== 'activo') {
            return null;
        }

        $rawKey = Str::random(32);
        $expiraEn = now()->addMinutes(self::MINUTOS_VIGENCIA);

        LlaveMaestra::create([
            'id_user_objetivo' => $idUserObjetivo,
            'connection_objetivo' => $connectionObjetivo,
            'generado_por' => $generadoPor,
            'token_hash' => hash('sha256', $rawKey),
            'expira_en' => $expiraEn,
            'created_at' => now(),
        ]);

        return ['key' => $rawKey, 'expira_en' => $expiraEn];
    }

    /**
     * @return array{id_user: int, connection: string, generado_por: int}|string|null null si la
     * llave no existe, ya se usó, está expirada o fue revocada; SESION_ABIERTA si la cuenta
     * destino ya tiene una sesión por llave abierta (la llave no se consume).
     */
    public function redimir(string $rawKey, string $ip): array|string|null
    {
        $llave = LlaveMaestra::where('token_hash', hash('sha256', $rawKey))
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->first();

        if (!$llave) {
            return null;
        }

        // Cache::add es atómico: dos canjes simultáneos para la misma cuenta no abren dos sesiones.
        // ponytail: dura lo mismo que el JWT (no hay refresh); se libera antes en logout.
        if (!Cache::add(
            $this->claveSesion($llave->connection_objetivo, $llave->id_user_objetivo),
            true,
            now()->addMinutes((int) config('jwt.ttl'))
        )) {
            return self::SESION_ABIERTA;
        }

        $llave->update(['usado_en' => now(), 'ip_uso' => $ip]);

        return [
            'id_user' => $llave->id_user_objetivo,
            'connection' => $llave->connection_objetivo,
            'generado_por' => $llave->generado_por,
        ];
    }

    /** Libera la cuenta para otro canje: logout de una sesión por llave, o canje que no llegó a emitir token. */
    public function cerrarSesion(string $connection, int $idUser): void
    {
        Cache::forget($this->claveSesion($connection, $idUser));
    }

    private function claveSesion(string $connection, int $idUser): string
    {
        return "llave_maestra_sesion:{$connection}:{$idUser}";
    }

    public function listar(): Collection
    {
        return LlaveMaestra::orderByDesc('created_at')->get();
    }

    /** true si se revocó; false si la llave no existe o ya estaba usada/expirada (nada
     * que revocar). No borra la fila — el historial de "fue cancelada" también es auditoría. */
    public function revocar(int $id): bool
    {
        $llave = LlaveMaestra::whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->find($id);

        if (!$llave) {
            return false;
        }

        $llave->update(['expira_en' => now()]);

        return true;
    }
}

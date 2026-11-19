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
     * @return array{id_user: int, connection: string, generado_por: int, llave_id: int}|string|null null si la
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
        // Guarda el id de la llave: el token lleva el mismo id (claim llave_maestra_id) y
        // sesionVigente() los compara, así un token de una sesión ya cerrada no revive
        // cuando otra llave abre una sesión nueva en la misma cuenta.
        if (!Cache::add(
            $this->claveSesion($llave->connection_objetivo, $llave->id_user_objetivo),
            $llave->id,
            now()->addMinutes((int) config('jwt.ttl'))
        )) {
            return self::SESION_ABIERTA;
        }

        $llave->update(['usado_en' => now(), 'ip_uso' => $ip]);

        return [
            'id_user' => $llave->id_user_objetivo,
            'connection' => $llave->connection_objetivo,
            'generado_por' => $llave->generado_por,
            'llave_id' => $llave->id,
        ];
    }

    /**
     * Libera la cuenta para otro canje: logout de una sesión por llave, canje que no llegó a
     * emitir token, o cierre desde administración. Con $llaveId solo la libera si la sesión
     * abierta es la de esa llave (el logout de un token viejo no cierra la sesión de otro).
     */
    public function cerrarSesion(string $connection, int $idUser, ?int $llaveId = null): void
    {
        if ($llaveId === null || $this->sesionVigente($connection, $idUser, $llaveId)) {
            Cache::forget($this->claveSesion($connection, $idUser));
        }
    }

    /**
     * true si el token de una sesión por llave sigue valiendo (lo consulta JwtFromCookie en
     * cada petición). Sin $llaveId (token emitido antes de que existiera el claim) basta con
     * que la cuenta tenga una sesión abierta; con él, tiene que ser la de esa misma llave.
     */
    public function sesionVigente(string $connection, int $idUser, ?int $llaveId): bool
    {
        $abierta = Cache::get($this->claveSesion($connection, $idUser));

        // (int): el store de Redis devuelve los números como string. `true` = marca de antes del claim.
        return $abierta !== null && ($llaveId === null || ($abierta !== true && (int) $abierta === $llaveId));
    }

    /** Cierre desde administración. false si esa llave no tiene una sesión abierta. */
    public function cerrarSesionDeLlave(int $id): bool
    {
        $llave = LlaveMaestra::whereNotNull('usado_en')->find($id);

        if (!$llave || !$this->sesionVigente($llave->connection_objetivo, $llave->id_user_objetivo, $this->idSesion($llave))) {
            return false;
        }

        Cache::forget($this->claveSesion($llave->connection_objetivo, $llave->id_user_objetivo));

        return true;
    }

    /** Id con el que se compara la marca: null si la marca es de antes del claim (valor `true`). */
    private function idSesion(LlaveMaestra $llave): ?int
    {
        return Cache::get($this->claveSesion($llave->connection_objetivo, $llave->id_user_objetivo)) === true ? null : $llave->id;
    }

    private function claveSesion(string $connection, int $idUser): string
    {
        return "llave_maestra_sesion:{$connection}:{$idUser}";
    }

    public function listar(): Collection
    {
        return LlaveMaestra::orderByDesc('created_at')->get();
    }

    /**
     * Llaves canjeadas cuya sesión sigue abierta: la marca de caché de redimir() existe (se
     * borra en logout y vence con el JWT). Solo la última canjeada por cuenta, porque esa
     * marca es por cuenta y no por llave.
     */
    public function sesionesAbiertas(): Collection
    {
        return LlaveMaestra::whereNotNull('usado_en')
            ->where('usado_en', '>', now()->subMinutes((int) config('jwt.ttl')))
            ->orderByDesc('usado_en')
            ->get()
            ->unique(fn (LlaveMaestra $llave) => "{$llave->connection_objetivo}:{$llave->id_user_objetivo}")
            ->filter(fn (LlaveMaestra $llave) => $this->sesionVigente($llave->connection_objetivo, $llave->id_user_objetivo, $this->idSesion($llave)))
            ->values();
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

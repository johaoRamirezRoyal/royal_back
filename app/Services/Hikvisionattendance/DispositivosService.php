<?php

namespace App\Services\Hikvisionattendance;

use App\Models\Hikvision\HikvisionDispositivo;
use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y baja de las terminales Hikvision del colegio (tabla hikvision_dispositivos
 * del tenant). El listado con estado de conexión vive en hikvisionattendanceService::devicesInfo.
 *
 * Ojo con los mensajes de error: apiResponse() responde 503 si el texto menciona "host" o
 * "conexión", por eso aquí se habla de "dirección".
 */
class DispositivosService
{
    public function guardar(array $datos, ?int $id = null): array
    {
        $dispositivo = $id === null ? new HikvisionDispositivo() : HikvisionDispositivo::find($id);

        if (!$dispositivo) {
            return ['error' => true, 'status' => 404, 'message' => 'La terminal no existe'];
        }

        // Al editar, una contraseña vacía conserva la guardada (nunca se envía al frontend).
        if (($datos['password'] ?? '') === '') {
            unset($datos['password']);
        }

        if ($dispositivo->exists === false && !isset($datos['password'])) {
            return ['error' => true, 'status' => 422, 'message' => 'Escribe la contraseña de la terminal'];
        }

        $dispositivo->fill($datos);

        $repetida = HikvisionDispositivo::where('device_id', "{$dispositivo->host}:{$dispositivo->puerto}")
            ->when($dispositivo->exists, fn ($q) => $q->where('id', '!=', $dispositivo->id))
            ->exists();

        if ($repetida) {
            return ['error' => true, 'status' => 422, 'message' => 'Ya hay una terminal registrada con esa dirección y puerto'];
        }

        // Cada terminal es de una sola institución: el webhook decide a qué colegio va una
        // marcación por la terminal que la envía.
        if ($otra = $this->institucionQueLaTiene("{$dispositivo->host}:{$dispositivo->puerto}")) {
            return ['error' => true, 'status' => 422, 'message' => "Esa dirección y puerto ya están registrados en otra institución ({$otra})"];
        }

        DB::transaction(function () use ($dispositivo) {
            // Una sola principal: es la que se usa para las capturas sin terminal elegida.
            if ($dispositivo->principal) {
                HikvisionDispositivo::when($dispositivo->exists, fn ($q) => $q->where('id', '!=', $dispositivo->id))
                    ->update(['principal' => false]);
            }

            $dispositivo->save();
        });

        return [
            'error' => false,
            'message' => $id === null ? 'Terminal agregada' : 'Terminal actualizada',
            'data' => $dispositivo->fresh(),
        ];
    }

    /** Nombre de otra institución que ya tiene registrada esta terminal, o null. */
    private function institucionQueLaTiene(string $deviceId): ?string
    {
        foreach (BasesDatosService::connectionsConUsuarios() as $connection) {
            if ($connection === config('database.default')) {
                continue;
            }

            try {
                if (HikvisionDispositivo::on($connection)->where('device_id', $deviceId)->exists()) {
                    return BasesDatosService::nombre($connection);
                }
            } catch (QueryException) {
                // Institución sin la tabla migrada todavía: no tiene terminales.
            }
        }

        return null;
    }

    public function eliminar(int $id): array
    {
        $dispositivo = HikvisionDispositivo::find($id);

        if (!$dispositivo) {
            return ['error' => true, 'status' => 404, 'message' => 'La terminal no existe'];
        }

        $dispositivo->delete();

        return ['error' => false, 'message' => 'Terminal eliminada'];
    }
}

<?php

namespace App\Services\LlegadasTardeEstudiantes;

use App\Models\LlegadasTarde\ConfiguracionLlegadasTarde;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use App\Services\Service;
use Exception;

class LlegadasTardeConfigService extends Service
{
    // Fila única de configuración (ver migración create_configuracion_llegadas_tarde_table).
    private const ID_CONFIG = 1;

    // Perfil "Coordinador" en la tabla perfiles.
    private const PERFIL_COORDINADOR = 26;

    public function __construct(private MailService $mailService) {}

    public function obtenerConfiguracion(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Configuración obtenida correctamente',
                'data' => ConfiguracionLlegadasTarde::findOrFail(self::ID_CONFIG),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener la configuración de llegadas tarde');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la configuración de llegadas tarde',
                'data' => null,
            ];
        }
    }

    /**
     * Actualiza la hora límite y/o la cantidad límite de llegadas tarde. Si hora_limite
     * cambia y $notificarCoordinador es true, avisa por correo a los coordinadores activos
     * (perfil 26) del nuevo horario — opt-in explícito porque no todo cambio de
     * configuración amerita una notificación.
     */
    public function actualizarConfiguracion(array $datos, bool $notificarCoordinador): array
    {
        try {
            $config = ConfiguracionLlegadasTarde::findOrFail(self::ID_CONFIG);
            $horaAnterior = $config->hora_limite;

            $config->update($datos);

            $horaCambio = array_key_exists('hora_limite', $datos) && $datos['hora_limite'] !== substr($horaAnterior, 0, 5);

            if ($horaCambio && $notificarCoordinador) {
                $this->notificarCambioHorarioACoordinador($config->hora_limite);
            }

            return [
                'error' => false,
                'message' => 'Configuración actualizada correctamente',
                'data' => $config,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la configuración de llegadas tarde');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la configuración de llegadas tarde',
                'data' => null,
            ];
        }
    }

    private function notificarCambioHorarioACoordinador(string $horaLimite): void
    {
        $correos = Usuario::where('perfil', self::PERFIL_COORDINADOR)
            ->where('estado', 'activo')
            ->whereNotNull('correo')
            ->pluck('correo')
            ->filter()
            ->all();

        if (empty($correos)) {
            return;
        }

        $horaCorta = substr($horaLimite, 0, 5);

        $this->mailService->sendGeneric(
            $correos,
            'Cambio en el horario límite de llegada tarde',
            "Se actualizó la hora límite para registrar llegada tarde de los estudiantes: a partir de ahora, cualquier ingreso después de las {$horaCorta} se marcará como llegada tarde."
        );
    }
}

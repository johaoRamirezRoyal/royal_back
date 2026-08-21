<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de mensajes de WhatsApp vía Meta Cloud API (WhatsApp Business).
 * Mismo espíritu que MailService: nunca lanza excepción, siempre devuelve bool, y
 * deja constancia en el log tanto de éxitos como de fallos, para que un problema de
 * WhatsApp nunca tumbe el flujo que lo dispara (ej. el registro de una llegada tarde).
 *
 * Solo soporta mensajes de plantilla (`type: template`), que es lo único permitido
 * para mensajes que inicia el negocio fuera de la ventana de 24h de servicio al
 * cliente — no hay envío de texto libre aquí. Las plantillas deben existir y estar
 * aprobadas en Meta Business Manager antes de poder usarse (ver
 * docs/whatsapp-llegadas-tarde.md).
 */
class WhatsAppService
{
    private ?string $phoneNumberId;

    private ?string $accessToken;

    private string $apiVersion;

    private bool $enabled;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->apiVersion = config('services.whatsapp.api_version', 'v21.0');
        $this->enabled = (bool) config('services.whatsapp.enabled', false);
    }

    /**
     * Normaliza a solo dígitos (formato que espera "to" en la Cloud API, sin "+").
     * Un celular colombiano guardado como 10 dígitos locales (sin indicativo) se
     * asume Colombia y se le antepone 57. Si ya trae indicativo (más de 10 dígitos)
     * se deja tal cual.
     */
    private function normalizarNumero(string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', $numero) ?? '';

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 10) {
            $digitos = '57'.$digitos;
        }

        return $digitos;
    }

    /**
     * Envía una plantilla a un único número. `$parametros` son los valores
     * posicionales {{1}}, {{2}}... del cuerpo de la plantilla — deben coincidir en
     * cantidad y orden con la plantilla aprobada en Meta, o la API responde error.
     */
    public function sendTemplate(string $to, string $template, array $parametros = [], ?string $lang = null): bool
    {
        $lang ??= config('services.whatsapp.template_lang', 'es');

        if (! $this->enabled) {
            Log::info('WhatsApp deshabilitado (WHATSAPP_ENABLED=false), no se envía', [
                'to' => $to,
                'template' => $template,
            ]);

            return false;
        }

        $numero = $this->normalizarNumero($to);

        if (! $numero || ! $this->phoneNumberId || ! $this->accessToken) {
            Log::warning('No se pudo enviar WhatsApp: número, phone_number_id o token faltante', [
                'to' => $to,
                'template' => $template,
            ]);

            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->baseUrl("https://graph.facebook.com/{$this->apiVersion}")
                ->post("/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $numero,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $lang],
                        'components' => empty($parametros) ? [] : [[
                            'type' => 'body',
                            'parameters' => array_map(
                                fn ($valor) => ['type' => 'text', 'text' => (string) $valor],
                                $parametros
                            ),
                        ]],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Error al enviar WhatsApp', [
                    'to' => $numero,
                    'template' => $template,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            Log::info('WhatsApp enviado', ['to' => $numero, 'template' => $template]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar WhatsApp', [
                'to' => $numero,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envía la misma plantilla a varios números (ej. todos los acudientes activos de
     * un estudiante). Devuelve true si al menos un envío tuvo éxito.
     */
    public function sendTemplateToMany(array $numbers, string $template, array $parametros = [], ?string $lang = null): bool
    {
        $enviado = false;

        foreach (array_unique(array_filter($numbers)) as $numero) {
            $enviado = $this->sendTemplate($numero, $template, $parametros, $lang) || $enviado;
        }

        return $enviado;
    }
}

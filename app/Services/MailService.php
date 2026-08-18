<?php

namespace App\Services;

use App\Mail\GenericMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Descarta direcciones que no cumplen RFC 2822 antes de enviar.
     * Un solo correo inválido en el lote hace que Mail::to()->send() lance
     * excepción para TODO el envío, bloqueando también a los destinatarios válidos.
     * FILTER_FLAG_EMAIL_UNICODE (RFC 6531/SMTPUTF8) permite tildes/ñ en la parte local
     * (ej. "alfredo.cañas@..."), que el validador ASCII-only por defecto rechaza.
     */
    private function filtrarCorreosValidos(array $recipients): array
    {
        $validos = array_values(array_filter(
            $recipients,
            fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false
        ));

        $invalidos = array_diff($recipients, $validos);

        if (! empty($invalidos)) {
            Log::warning('Se omitieron destinatarios con correo inválido', ['invalidos' => array_values($invalidos)]);
        }

        return $validos;
    }

    public function sendView(array|string $to, string $subject, string $view, array $data = []): array
    {
        try {
            $recipients = $this->filtrarCorreosValidos(is_string($to) ? [$to] : $to);

            if (empty($recipients)) {
                Log::warning('Intento de envío de correo sin destinatarios.');
                return [
                    'error' => true,
                    'message' => 'Intento de envío de correo sin destinatarios.',
                    'data' => [],
                ];
            }

            Mail::send($view, $data, function ($message) use ($recipients, $subject) {
                $message->to($recipients)->subject($subject);
            });

            Log::info('Correo con plantilla enviado', [
                'to' => $recipients,
                'subject' => $subject,
                'view' => $view,
            ]);

            return [
                'error' => false,
                'message' => 'Correo enviado correctamente',
                'data' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Error al enviar correo con plantilla', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => true,
                'message' => 'Correo NO enviado: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    
    public function sendGeneric(array|string $to, string $titulo, string $contenido): array
    {
        try {
            $recipients = $this->filtrarCorreosValidos(is_string($to) ? [$to] : $to);

            if (empty($recipients)) {
                Log::warning('Intento de envío de correo sin destinatarios.');
                return [
                    "error" => true,
                    "message" => "Intento de envío de correo sin destinatarios.",
                    "data" => []
                ];
            }

            Mail::to($recipients)->send(new GenericMail($titulo, $contenido));

            Log::info('Correo genérico enviado', [
                'to' => $recipients,
                'subject' => $titulo,
            ]);

            return [
                'error' => false,
                'message' => 'Correo genérico enviado correctamente',
                'data' => []
            ];
        } catch (\Throwable $e) {
            Log::error('Error al enviar correo genérico', [
                'to' => $to,
                'subject' => $titulo,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => true,
                'message' => 'Correo genérico NO enviado',
                'data' => []
            ];
        }
    }

    public function send(array|string $to, Mailable $mailable): bool
    {
        try {
            $recipients = $this->filtrarCorreosValidos(is_string($to) ? [$to] : $to);

            if (empty($recipients)) {
                Log::warning('Intento de envío de correo sin destinatarios.');
                return false;
            }

            Mail::to($recipients)->send($mailable);

            Log::info('Correo enviado', [
                'to' => $recipients,
                'mailable' => get_class($mailable),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al enviar correo', [
                'to' => $to,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

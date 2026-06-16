<?php

namespace App\Services;

use App\Mail\GenericMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public function sendGeneric(array|string $to, string $titulo, string $contenido): array
    {
        try {
            $recipients = is_string($to) ? [$to] : $to;

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
            $recipients = is_string($to) ? [$to] : $to;

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

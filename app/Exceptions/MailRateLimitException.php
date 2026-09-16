<?php

namespace App\Exceptions;

/**
 * Señal de "el proveedor SMTP nos está limitando el envío ahora" (ej. cPanel/Exim
 * "Max Emails Per Hour" -> 452-4.5.3 "Your message has too many recipients"). Distinta
 * de un fallo puntual de un destinatario: quien la reciba debe cortar el lote en vez de
 * seguir intentando uno por uno contra un límite que no se va a levantar en el acto.
 */
class MailRateLimitException extends \RuntimeException
{
}

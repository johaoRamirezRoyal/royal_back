<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Limpia el HTML que redacta el usuario en el editor (Tiptap) antes de meterlo en un
 * correo: lista blanca de etiquetas de formato básico, enlaces solo http/https/mailto,
 * sin atributos de estilo ni eventos. Todo lo demás (script, style, img, on*) se descarta.
 */
class HtmlCorreoSanitizer
{
    private const ETIQUETAS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'];

    public static function limpiar(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(20000);

        foreach (self::ETIQUETAS as $etiqueta) {
            $config = $config->allowElement($etiqueta);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}

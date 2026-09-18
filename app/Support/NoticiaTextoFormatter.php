<?php

namespace App\Support;

/**
 * Markup ligero para el mensaje de Noticias: **negrita**, _cursiva_,
 * {color:texto} o {#2563eb:texto}. Escapa todo el texto primero (e()) y solo
 * despliega sus propios delimitadores sobre el resultado ya escapado — nunca
 * HTML arbitrario del usuario. El grupo de color queda restringido a
 * [#a-zA-Z0-9]+ por el propio regex, así que no puede escapar el atributo
 * style ni inyectar nada.
 * No soporta anidar (ej. **_texto_** no compone) — YAGNI hasta que se pida.
 */
class NoticiaTextoFormatter
{
    public static function aHtml(?string $texto): string
    {
        if (!$texto) {
            return '';
        }

        $html = e($texto);
        $html = preg_replace('/\{(#[0-9a-fA-F]{3,6}|[a-zA-Z]+):(.+?)\}/s', '<span style="color:$1">$2</span>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/_(.+?)_/s', '<em>$1</em>', $html);

        return nl2br($html);
    }
}

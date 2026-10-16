<?php

namespace App\Services\Noticias;

use GdImage;
use RuntimeException;

/**
 * Compone la felicitación de cumpleaños con GD: la imagen de la plantilla + el nombre y
 * la foto de la persona en las posiciones de `elementos`. La usan la vista previa del
 * editor y la generación automática, así la vista previa es exactamente la imagen que
 * se publica.
 *
 * `elementos` guarda todo en fracciones de la imagen (independiente de su resolución y
 * del zoom del editor):
 * - nombre: `x`/`y` = punto de anclaje (x según la alineación; y = centro vertical de la
 *   tinta del texto), `tamano` = alto de fuente / ancho de la imagen, `ancho_max` = ancho
 *   máximo / ancho de la imagen (si el nombre no cabe, se reduce la fuente).
 * - foto: `x`/`y` = centro, `ancho`/`alto` = fracción del ANCHO de la imagen (los dos,
 *   para que un círculo siga siendo círculo), `radio` = fracción del lado menor (0–0,5),
 *   `borde` = fracción del ancho de la imagen, `capa` = encima o debajo de la plantilla
 *   (debajo solo tiene sentido con un PNG con transparencia: la plantilla enmarca la foto).
 *
 * El editor del frontend (PlantillaEditor) reproduce estas mismas reglas en SVG.
 */
class CumpleanosImagen
{
    /** Tipografías disponibles (resources/fonts/cumpleanos, todas SIL OFL 1.1). Llave = `${peso}-${estilo}`. */
    public const FUENTES = [
        'poppins' => [
            'nombre' => 'Poppins',
            'archivos' => [
                '400-normal' => 'Poppins-Regular.ttf',
                '400-italic' => 'Poppins-Italic.ttf',
                '700-normal' => 'Poppins-Bold.ttf',
                '700-italic' => 'Poppins-BoldItalic.ttf',
            ],
        ],
        'pt-serif' => [
            'nombre' => 'PT Serif',
            'archivos' => [
                '400-normal' => 'PT_Serif-Web-Regular.ttf',
                '400-italic' => 'PT_Serif-Web-Italic.ttf',
                '700-normal' => 'PT_Serif-Web-Bold.ttf',
                '700-italic' => 'PT_Serif-Web-BoldItalic.ttf',
            ],
        ],
        'lilita-one' => [
            'nombre' => 'Lilita One',
            'archivos' => ['400-normal' => 'LilitaOne-Regular.ttf'],
        ],
        'pacifico' => [
            'nombre' => 'Pacifico',
            'archivos' => ['400-normal' => 'Pacifico-Regular.ttf'],
        ],
        'great-vibes' => [
            'nombre' => 'Great Vibes',
            'archivos' => ['400-normal' => 'GreatVibes-Regular.ttf'],
        ],
    ];

    public const ELEMENTOS_DEFECTO = [
        'nombre' => [
            'x' => 0.5,
            'y' => 0.86,
            'tamano' => 0.065,
            'fuente' => 'poppins',
            'peso' => 700,
            'estilo' => 'normal',
            'color' => '#ffffff',
            'alineacion' => 'center',
            'ancho_max' => 0.84,
            'mayusculas' => false,
            'formato' => 'nombre_apellido',
        ],
        'foto' => [
            'x' => 0.5,
            'y' => 0.6,
            'ancho' => 0.4,
            'alto' => 0.4,
            'forma' => 'circulo',
            'radio' => 0.12,
            'borde' => 0.008,
            'color_borde' => '#ffffff',
            'ajuste' => 'cover',
            'capa' => 'encima',
        ],
    ];

    private const FORMATOS = ['primer_nombre', 'nombre_apellido', 'completo'];

    // Partículas que van en minúscula dentro de un nombre ("María de la Cruz").
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y'];

    public static function rutaFuente(string $archivo): string
    {
        return resource_path('fonts/cumpleanos/' . $archivo);
    }

    /** Archivo de la variante pedida; si la familia no la tiene, la más cercana (mismo peso, luego regular). */
    public static function archivoFuente(string $fuente, int $peso, string $estilo): string
    {
        $archivos = (self::FUENTES[$fuente] ?? self::FUENTES['poppins'])['archivos'];

        return $archivos["{$peso}-{$estilo}"] ?? $archivos["{$peso}-normal"] ?? $archivos['400-normal'];
    }

    /** Completa con los valores por defecto y acota cada valor — `elementos` llega del cliente. */
    public static function normalizar(?array $elementos): array
    {
        $n = array_merge(self::ELEMENTOS_DEFECTO['nombre'], $elementos['nombre'] ?? []);
        $f = array_merge(self::ELEMENTOS_DEFECTO['foto'], $elementos['foto'] ?? []);
        $acotar = fn ($v, float $min, float $max) => max($min, min($max, (float) $v));
        $color = fn ($v, string $defecto) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $defecto;
        $opcion = fn ($v, array $validas) => in_array($v, $validas, true) ? $v : $validas[0];

        return [
            'nombre' => [
                'x' => $acotar($n['x'], 0, 1),
                'y' => $acotar($n['y'], 0, 1),
                'tamano' => $acotar($n['tamano'], 0.01, 0.3),
                'fuente' => array_key_exists($n['fuente'], self::FUENTES) ? $n['fuente'] : 'poppins',
                'peso' => (int) $n['peso'] >= 600 ? 700 : 400,
                'estilo' => $opcion($n['estilo'], ['normal', 'italic']),
                'color' => $color($n['color'], '#ffffff'),
                'alineacion' => $opcion($n['alineacion'], ['center', 'left', 'right']),
                'ancho_max' => $acotar($n['ancho_max'], 0.1, 1),
                'mayusculas' => (bool) $n['mayusculas'],
                'formato' => $opcion($n['formato'], ['nombre_apellido', 'primer_nombre', 'completo']),
            ],
            'foto' => [
                'x' => $acotar($f['x'], 0, 1),
                'y' => $acotar($f['y'], 0, 1),
                'ancho' => $acotar($f['ancho'], 0.03, 1),
                'alto' => $acotar($f['alto'], 0.03, 1.5),
                'forma' => $opcion($f['forma'], ['circulo', 'rectangulo']),
                'radio' => $acotar($f['radio'], 0, 0.5),
                'borde' => $acotar($f['borde'], 0, 0.05),
                'color_borde' => $color($f['color_borde'], '#ffffff'),
                'ajuste' => $opcion($f['ajuste'], ['cover', 'contain']),
                'capa' => $opcion($f['capa'], ['encima', 'debajo']),
            ],
        ];
    }

    /**
     * Nombre a mostrar. Limpia los datos legacy (apellido "." o ",", todo el nombre en
     * `nombre`, mayúsculas sostenidas) y lo pasa a "Tipo Título".
     * ponytail: sin apellido, con 3+ palabras se asume que las dos últimas son apellidos —
     * heurística, falla con nombres de una sola palabra + dos apellidos compuestos.
     */
    public static function formatearNombre(?string $nombre, ?string $apellido, string $formato, bool $mayusculas = false): string
    {
        $palabras = fn (?string $s) => array_values(array_filter(
            preg_split('/\s+/u', trim((string) $s)) ?: [],
            fn ($p) => preg_match('/\p{L}/u', $p),
        ));
        $nombres = $palabras($nombre);
        $apellidos = $palabras($apellido);

        if (!$apellidos && count($nombres) >= 3) {
            $apellidos = array_slice($nombres, -2);
            $nombres = array_slice($nombres, 0, -2);
        } elseif (!$apellidos && count($nombres) === 2) {
            $apellidos = [$nombres[1]];
            $nombres = [$nombres[0]];
        }

        $partes = match (in_array($formato, self::FORMATOS, true) ? $formato : 'nombre_apellido') {
            'primer_nombre' => array_slice($nombres, 0, 1),
            'completo' => array_merge($nombres, $apellidos),
            default => array_merge(array_slice($nombres, 0, 1), array_slice($apellidos, 0, 1)),
        };

        $texto = implode(' ', array_map(
            fn ($p, $i) => $i > 0 && in_array(mb_strtolower($p), self::PARTICULAS, true)
                ? mb_strtolower($p)
                : mb_convert_case(mb_strtolower($p), MB_CASE_TITLE),
            $partes,
            array_keys($partes),
        ));

        return $mayusculas ? mb_strtoupper($texto) : $texto;
    }

    /**
     * Felicitación como JPEG. `$rutaFoto` null (o ilegible) dibuja un relleno gris en el
     * lugar de la foto — solo pasa en la vista previa: la generación exige foto.
     */
    public function componer(string $rutaPlantilla, array $elementos, string $nombre, ?string $rutaFoto): string
    {
        $e = self::normalizar($elementos);
        $base = $this->cargar($rutaPlantilla);
        $w = imagesx($base);
        $h = imagesy($base);

        $lienzo = imagecreatetruecolor($w, $h);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagealphablending($lienzo, true);

        $foto = $rutaFoto ? $this->cargarSilencioso($rutaFoto) : null;
        $capaFoto = $this->capaFoto($foto, $e['foto'], $w);
        $fw = (int) round($e['foto']['ancho'] * $w);
        $fh = (int) round($e['foto']['alto'] * $w);
        $borde = (int) round($e['foto']['borde'] * $w);
        $fx = (int) round($e['foto']['x'] * $w - $fw / 2) - $borde;
        $fy = (int) round($e['foto']['y'] * $h - $fh / 2) - $borde;

        if ($e['foto']['capa'] === 'debajo') {
            imagecopy($lienzo, $capaFoto, $fx, $fy, 0, 0, imagesx($capaFoto), imagesy($capaFoto));
            imagecopy($lienzo, $base, 0, 0, 0, 0, $w, $h);
        } else {
            imagecopy($lienzo, $base, 0, 0, 0, 0, $w, $h);
            imagecopy($lienzo, $capaFoto, $fx, $fy, 0, 0, imagesx($capaFoto), imagesy($capaFoto));
        }

        $this->dibujarNombre($lienzo, $e['nombre'], $nombre, $w, $h);

        ob_start();
        imagejpeg($lienzo, null, 90);

        return ob_get_clean();
    }

    /** Dimensiones y si la imagen tiene píxeles transparentes (para ofrecer "foto debajo"). */
    public function inspeccionar(string $ruta): array
    {
        $im = $this->cargar($ruta);
        $w = imagesx($im);
        $h = imagesy($im);
        // Muestra reducida: basta para saber si hay algún píxel con transparencia.
        $muestra = imagescale($im, min(160, $w));
        $transparente = false;

        for ($y = 0, $mh = imagesy($muestra), $mw = imagesx($muestra); $y < $mh && !$transparente; $y++) {
            for ($x = 0; $x < $mw; $x++) {
                if (((imagecolorat($muestra, $x, $y) >> 24) & 0x7F) > 0) {
                    $transparente = true;
                    break;
                }
            }
        }

        return ['ancho' => $w, 'alto' => $h, 'transparente' => $transparente];
    }

    private function dibujarNombre(GdImage $lienzo, array $n, string $texto, int $w, int $h): void
    {
        $archivo = self::rutaFuente(self::archivoFuente($n['fuente'], $n['peso'], $n['estilo']));
        $px = $n['tamano'] * $w;
        $max = $n['ancho_max'] * $w;

        // GD mide en puntos a 96 ppp: px = pt · 96/72. Si no cabe, se reduce hasta el ancho máximo.
        $caja = imagettfbbox($px * 0.75, 0, $archivo, $texto);
        $ancho = $caja[2] - $caja[0];
        if ($ancho > $max && $ancho > 0) {
            $px *= $max / $ancho;
            $caja = imagettfbbox($px * 0.75, 0, $archivo, $texto);
        }

        $arriba = min($caja[5], $caja[7]);
        $abajo = max($caja[1], $caja[3]);
        $base = $n['y'] * $h - ($arriba + $abajo) / 2;
        $x = $n['x'] * $w;
        $origen = match ($n['alineacion']) {
            'left' => $x - $caja[0],
            'right' => $x - $caja[2],
            default => $x - ($caja[0] + $caja[2]) / 2,
        };

        [$r, $g, $b] = sscanf($n['color'], '#%02x%02x%02x');
        imagettftext($lienzo, $px * 0.75, 0, (int) round($origen), (int) round($base), imagecolorallocate($lienzo, $r, $g, $b), $archivo, $texto);
    }

    /** Foto recortada a su forma (con antialias) y su borde, como imagen con transparencia. */
    private function capaFoto(?GdImage $foto, array $f, int $anchoImagen): GdImage
    {
        $w = max(1, (int) round($f['ancho'] * $anchoImagen));
        $h = max(1, (int) round($f['alto'] * $anchoImagen));
        $b = (int) round($f['borde'] * $anchoImagen);
        $radio = $f['forma'] === 'circulo' ? null : $f['radio'] * min($w, $h);

        $contenido = $this->lienzoTransparente($w, $h);
        if ($foto) {
            $sw = imagesx($foto);
            $sh = imagesy($foto);
            if ($f['ajuste'] === 'cover') {
                $escala = max($w / $sw, $h / $sh);
                $cw = $w / $escala;
                $ch = $h / $escala;
                imagecopyresampled($contenido, $foto, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, (int) $cw, (int) $ch);
            } else {
                $escala = min($w / $sw, $h / $sh);
                $dw = (int) round($sw * $escala);
                $dh = (int) round($sh * $escala);
                imagecopyresampled($contenido, $foto, (int) (($w - $dw) / 2), (int) (($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
            }
        } else {
            imagefilledrectangle($contenido, 0, 0, $w, $h, imagecolorallocate($contenido, 203, 210, 222));
        }

        $interior = $this->mascara($w, $h, $radio);
        $this->aplicarMascara($contenido, $interior);

        $capa = $this->lienzoTransparente($w + 2 * $b, $h + 2 * $b);
        if ($b > 0) {
            // Anillo = forma exterior menos la interior desplazada por el grosor del borde.
            $exterior = $this->mascara($w + 2 * $b, $h + 2 * $b, $radio === null ? null : $radio + $b);
            [$r, $g, $bl] = sscanf($f['color_borde'], '#%02x%02x%02x');
            for ($y = 0, $th = $h + 2 * $b, $tw = $w + 2 * $b; $y < $th; $y++) {
                for ($x = 0; $x < $tw; $x++) {
                    $dentro = ($x >= $b && $y >= $b && $x < $w + $b && $y < $h + $b) ? (imagecolorat($interior, $x - $b, $y - $b) & 0xFF) : 0;
                    $cobertura = max(0, (imagecolorat($exterior, $x, $y) & 0xFF) - $dentro);
                    if ($cobertura > 0) {
                        imagesetpixel($capa, $x, $y, imagecolorallocatealpha($capa, $r, $g, $bl, 127 - intdiv($cobertura * 127, 255)));
                    }
                }
            }
        }

        imagealphablending($capa, true);
        imagecopy($capa, $contenido, $b, $b, 0, 0, $w, $h);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);

        return $capa;
    }

    /** Cobertura 0–255 (canal azul) de la forma, dibujada a mayor escala y reducida = antialias. */
    private function mascara(int $w, int $h, ?float $radio): GdImage
    {
        $k = max(1, min(4, intdiv(2400, max($w, $h))));
        $grande = imagecreatetruecolor($w * $k, $h * $k);
        $blanco = imagecolorallocate($grande, 255, 255, 255);

        if ($radio === null) {
            imagefilledellipse($grande, intdiv($w * $k, 2), intdiv($h * $k, 2), $w * $k, $h * $k, $blanco);
        } else {
            $r = (int) round(min($radio, min($w, $h) / 2) * $k);
            $x2 = $w * $k - 1;
            $y2 = $h * $k - 1;
            imagefilledrectangle($grande, $r, 0, $x2 - $r, $y2, $blanco);
            imagefilledrectangle($grande, 0, $r, $x2, $y2 - $r, $blanco);
            if ($r > 0) {
                foreach ([[$r, $r], [$x2 - $r, $r], [$r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
                    imagefilledellipse($grande, $cx, $cy, 2 * $r, 2 * $r, $blanco);
                }
            }
        }

        $mascara = imagecreatetruecolor($w, $h);
        imagecopyresampled($mascara, $grande, 0, 0, 0, 0, $w, $h, $w * $k, $h * $k);

        return $mascara;
    }

    private function aplicarMascara(GdImage $im, GdImage $mascara): void
    {
        imagealphablending($im, false);
        for ($y = 0, $h = imagesy($im), $w = imagesx($im); $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $cobertura = imagecolorat($mascara, $x, $y) & 0xFF;
                $c = imagecolorat($im, $x, $y);
                $opacidad = 127 - (($c >> 24) & 0x7F);
                $alfa = 127 - intdiv($opacidad * $cobertura, 255);
                imagesetpixel($im, $x, $y, ($alfa << 24) | ($c & 0xFFFFFF));
            }
        }
        imagesavealpha($im, true);
    }

    private function lienzoTransparente(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagesavealpha($im, true);

        return $im;
    }

    private function cargar(string $ruta): GdImage
    {
        $im = $this->cargarSilencioso($ruta);
        if (!$im) {
            throw new RuntimeException('No se pudo leer la imagen de la plantilla.');
        }

        return $im;
    }

    /** Imagen como truecolor con alfa, con la orientación EXIF aplicada (fotos de celular). */
    private function cargarSilencioso(string $ruta): ?GdImage
    {
        if (!is_file($ruta) || !($datos = @file_get_contents($ruta)) || !($im = @imagecreatefromstring($datos))) {
            return null;
        }

        imagepalettetotruecolor($im);
        $orientacion = function_exists('exif_read_data') ? (@exif_read_data($ruta)['Orientation'] ?? 1) : 1;
        $angulo = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;
        if ($angulo) {
            $im = imagerotate($im, $angulo, 0);
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);

        return $im;
    }
}

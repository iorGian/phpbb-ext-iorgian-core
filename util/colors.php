<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Validación y contraste de colores hexadecimales (para insignias de colores).
 */
class colors
{
    /**
     * Normaliza "#abc" / "ABCDEF" / "#AbCdEf" a "#aabbcc" en minúsculas, o null si no es válido.
     */
    public function normalize(string $color): ?string
    {
        $color = ltrim(trim($color), '#');

        if (!preg_match('/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $color))
        {
            return null;
        }

        if (strlen($color) === 3)
        {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        return '#' . strtolower($color);
    }

    /**
     * Value for <input type="color">: the browser accepts only "#rrggbb" and never an empty value.
     */
    public function picker_value(string $color, string $fallback = '#808080'): string
    {
        return $this->normalize($color) ?? $fallback;
    }

    /**
     * Color de texto legible (#000000 o #ffffff) para un fondo dado (luminancia relativa WCAG).
     */
    public function contrast_text(string $background): string
    {
        $hex = $this->normalize($background);
        if ($hex === null)
        {
            return '#000000';
        }

        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];

        foreach ($channels as &$c)
        {
            $c = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        unset($c);

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return ($luminance > 0.179) ? '#000000' : '#ffffff';
    }
}

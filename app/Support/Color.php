<?php

namespace App\Support;

/**
 * Helper pentru culorile alese de owner (#RRGGBB). Aceeași regulă e în `resources/js/program-wizard.js`.
 */
final class Color
{
    public const DARK = '#012156';

    public const LIGHT = '#ffffff';

    public const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Culoarea textului lizibilă pe fundalul dat: închis sau alb, după contrastul WCAG mai bun.
     */
    public static function readableOn(string $hex): string
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return self::DARK;
        }

        $luminance = self::luminance($m[1], $m[2], $m[3]);
        $dark = self::luminance(substr(self::DARK, 1, 2), substr(self::DARK, 3, 2), substr(self::DARK, 5, 2));

        // Contrastul real cu textul închis folosit (DARK), nu cu negru pur.
        return ($luminance + 0.05) / ($dark + 0.05) >= 1.05 / ($luminance + 0.05) ? self::DARK : self::LIGHT;
    }

    /**
     * Luminanța relativă WCAG a unei culori date pe canale hex („01”, „21”, „56”).
     */
    private static function luminance(string $r, string $g, string $b): float
    {
        $channel = function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    public static function isHex(?string $value): bool
    {
        return $value !== null && preg_match(self::HEX_PATTERN, $value) === 1;
    }
}

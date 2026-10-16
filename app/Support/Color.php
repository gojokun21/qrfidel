<?php

namespace App\Support;

/**
 * Helper pentru culorile alese de owner (#RRGGBB). Aceeași regulă e în `resources/js/program-wizard.js`.
 */
final class Color
{
    public const DARK = '#1c1410';

    public const LIGHT = '#ffffff';

    public const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Luminanța relativă a lui DARK (#1c1410).
     */
    private const DARK_LUMINANCE = 0.0078;

    /**
     * Culoarea textului lizibilă pe fundalul dat: închis sau alb, după contrastul WCAG mai bun.
     */
    public static function readableOn(string $hex): string
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return self::DARK;
        }

        $channel = function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel($m[1]) + 0.7152 * $channel($m[2]) + 0.0722 * $channel($m[3]);

        // Contrastul real cu textul închis folosit (DARK), nu cu negru pur.
        return ($luminance + 0.05) / (self::DARK_LUMINANCE + 0.05) >= 1.05 / ($luminance + 0.05) ? self::DARK : self::LIGHT;
    }

    public static function isHex(?string $value): bool
    {
        return $value !== null && preg_match(self::HEX_PATTERN, $value) === 1;
    }
}

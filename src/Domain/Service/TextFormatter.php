<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Service;

final class TextFormatter
{
    /**
     * Format a numerical price into localized Japanese Yen currency representation.
     */
    public static function formatPrice(float|int|string $price): string
    {
        return '¥' . number_format((float)$price, 0);
    }

    /**
     * Strict HTML sanitization against XSS for DOM rendering.
     */
    public static function sanitize(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Safely replace template placeholders {{key}} with values.
     *
     * @param string $text Template string
     * @param array<string, mixed> $placeholders Placeholder mapping
     * @param bool $escapeValues Whether dynamic values should be XSS-escaped
     */
    public static function replacePlaceholders(string $text, array $placeholders, bool $escapeValues = false): string
    {
        foreach ($placeholders as $key => $value) {
            $valStr = (string)$value;
            if ($escapeValues) {
                $valStr = htmlspecialchars($valStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $text = str_replace('{{' . $key . '}}', $valStr, $text);
        }
        return $text;
    }

    /**
     * Translate order or entity status using localized language keys if available.
     */
    public static function translateStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }
        $key = 'status_' . $status;
        if (function_exists('__')) {
            $translated = (string)__($key);
            return $translated !== $key ? $translated : ucfirst($status);
        }
        return ucfirst($status);
    }
}

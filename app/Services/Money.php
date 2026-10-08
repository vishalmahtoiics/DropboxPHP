<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Money is handled as DECIMAL in MySQL and as strings in PHP until it is
 * formatted, so no float rounding ever touches a stored price.
 */
final class Money
{
    /** Validate and normalise an admin-entered amount: "1,299.50" → "1299.50", "" → null. */
    public static function parse(mixed $value): ?string
    {
        $value = trim(str_replace([',', ' '], '', (string) $value));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            return null;
        }
        return $value;
    }

    public static function format(mixed $amount, string $symbol = '₹', string $currency = 'INR', bool $decimals = false): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }
        $amount = (string) $amount;
        [$int, $frac] = array_pad(explode('.', $amount, 2), 2, '00');
        $int = ltrim($int, '0') ?: '0';
        $grouped = $currency === 'INR' ? self::indianGrouping($int) : number_format((float) $int, 0, '.', ',');
        $frac = str_pad(substr($frac, 0, 2), 2, '0');
        $show = $decimals || $frac !== '00';
        return $symbol . $grouped . ($show ? '.' . $frac : '');
    }

    private static function indianGrouping(string $int): string
    {
        if (strlen($int) <= 3) {
            return $int;
        }
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
        return $rest . ',' . $last3;
    }
}

<?php

namespace App\Domain\Catalog\Support;

use InvalidArgumentException;

/**
 * Exact fixed-point money helpers. Amounts are decimal strings ("6490.00") and are
 * converted to integer minor units (centavos) for comparison and arithmetic, never floats.
 */
final class Money
{
    private const SYMBOLS = [
        'PHP' => '₱',
        'USD' => '$',
    ];

    /**
     * Convert a decimal string with at most two fraction digits into integer minor units.
     */
    public static function cents(string $amount): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }

        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $cents = (int) ($matches[2].$fraction);

        return $matches[1] === '-' ? -$cents : $cents;
    }

    /**
     * Format minor units for display, e.g. 649000 PHP => "₱6,490.00".
     */
    public static function formatCents(int $cents, string $currency = 'PHP'): string
    {
        $absolute = abs($cents);
        $symbol = self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';
        $sign = $cents < 0 ? '-' : '';

        return sprintf('%s%s%s.%02d', $sign, $symbol, number_format(intdiv($absolute, 100)), $absolute % 100);
    }

    public static function format(string $amount, string $currency = 'PHP'): string
    {
        return self::formatCents(self::cents($amount), $currency);
    }
}

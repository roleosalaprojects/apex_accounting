<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Parses a DECIMAL(15,4) quantity into integer ten-thousandths so line maths
 * stay integer-only (no floats in money paths, §16.3).
 */
final class Quantity
{
    public const SCALE = 10_000;

    /**
     * "360" -> 3_600_000 ; "1.5" -> 15_000 ; "0.0001" -> 1
     */
    public static function toUnits(string|int|float $qty): int
    {
        $string = is_string($qty) ? trim($qty) : (string) $qty;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new InvalidArgumentException("Invalid quantity: {$string}");
        }

        $negative = str_starts_with($string, '-');
        $string = ltrim($string, '-');

        $parts = explode('.', $string);
        $whole = (int) $parts[0];
        $fraction = $parts[1] ?? '';
        $fraction = substr(str_pad($fraction, 4, '0'), 0, 4);

        $units = ($whole * self::SCALE) + (int) $fraction;

        return $negative ? -$units : $units;
    }

    /**
     * Render integer ten-thousandths back to a DECIMAL(15,4) string.
     */
    public static function fromUnits(int $units): string
    {
        $negative = $units < 0;
        $units = abs($units);
        $whole = intdiv($units, self::SCALE);
        $fraction = str_pad((string) ($units % self::SCALE), 4, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    /**
     * Multiply a unit price (centavos) by a quantity, rounding half up to the
     * centavo. Quantities are non-negative on real document lines.
     */
    public static function extend(int $unitPrice, int $units): int
    {
        $num = $unitPrice * $units;

        if ($num < 0) {
            return -intdiv((-$num * 2) + self::SCALE, self::SCALE * 2);
        }

        return intdiv(($num * 2) + self::SCALE, self::SCALE * 2);
    }
}

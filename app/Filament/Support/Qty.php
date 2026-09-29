<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Quantity;

/** Quantities for display: 1,250 · 12.5 · −3 (no trailing zeros). */
final class Qty
{
    /** From integer ten-thousandths (valuations, summed lines). */
    public static function units(int $units): string
    {
        $abs = abs($units);
        $fraction = rtrim(str_pad((string) ($abs % Quantity::SCALE), 4, '0', STR_PAD_LEFT), '0');

        return ($units < 0 ? '−' : '').number_format(intdiv($abs, Quantity::SCALE)).($fraction === '' ? '' : '.'.$fraction);
    }

    /** From a DECIMAL(15,4) column as the database returns it. */
    public static function decimal(string|int|float $qty): string
    {
        return self::units(Quantity::toUnits((string) $qty));
    }
}

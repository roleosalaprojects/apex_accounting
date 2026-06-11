<?php

declare(strict_types=1);

namespace App\Services\Tax;

use InvalidArgumentException;

/**
 * The single home of VAT arithmetic (§5.2). Everything is integer-centavo and
 * the one rounding rule — round half up to the centavo — lives here so it is
 * identical everywhere.
 */
final class VatMath
{
    /**
     * Split a VAT-inclusive amount at the given rate (basis points).
     * 12% inclusive: base = round(amount × 10000 / 11200), vat = amount − base.
     */
    public function fromInclusive(int $amount, int $rateBp): VatBreakdown
    {
        if ($rateBp === 0) {
            return new VatBreakdown($amount, 0);
        }

        $base = $this->roundDiv($amount * 10_000, 10_000 + $rateBp);

        return new VatBreakdown($base, $amount - $base);
    }

    /**
     * Split a VAT-exclusive amount at the given rate: vat = round(base × rate).
     */
    public function fromExclusive(int $base, int $rateBp): VatBreakdown
    {
        if ($rateBp === 0) {
            return new VatBreakdown($base, 0);
        }

        $vat = $this->roundDiv($base * $rateBp, 10_000);

        return new VatBreakdown($base, $vat);
    }

    /**
     * Half-up rounding of a non-negative integer division num/den.
     */
    public function roundDiv(int $num, int $den): int
    {
        if ($den <= 0) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }
        if ($num < 0) {
            // Symmetric half-up away from zero for negatives.
            return -intdiv((-$num * 2) + $den, $den * 2);
        }

        return intdiv(($num * 2) + $den, $den * 2);
    }
}

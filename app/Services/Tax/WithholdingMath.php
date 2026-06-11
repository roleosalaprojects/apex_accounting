<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * Expanded Withholding Tax (EWT) is computed on the VAT-exclusive base (§7),
 * using the single half-up rounding rule from VatMath.
 */
final class WithholdingMath
{
    public function __construct(private readonly VatMath $vat) {}

    public function compute(int $base, int $rateBp): int
    {
        if ($rateBp === 0 || $base === 0) {
            return 0;
        }

        return $this->vat->roundDiv($base * $rateBp, 10_000);
    }
}

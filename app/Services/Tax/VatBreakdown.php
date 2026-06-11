<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * Result of splitting a line amount into its VAT-exclusive base and the VAT.
 * Both values are integer centavos.
 */
final class VatBreakdown
{
    public function __construct(
        public readonly int $base,
        public readonly int $vat,
    ) {}

    public function total(): int
    {
        return $this->base + $this->vat;
    }
}

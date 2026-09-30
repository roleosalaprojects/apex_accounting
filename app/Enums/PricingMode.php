<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PricingMode: string implements HasLabel
{
    case VatInclusive = 'vat_inclusive';
    case VatExclusive = 'vat_exclusive';

    public function getLabel(): string
    {
        return match ($this) {
            self::VatInclusive => 'VAT-inclusive prices',
            self::VatExclusive => 'VAT-exclusive prices',
        };
    }
}

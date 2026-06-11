<?php

declare(strict_types=1);

namespace App\Enums;

enum PricingMode: string
{
    case VatInclusive = 'vat_inclusive';
    case VatExclusive = 'vat_exclusive';
}

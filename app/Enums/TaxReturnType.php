<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxReturnType: string
{
    case Vat2550Q = '2550Q';
    case Ewt1601EQ = '1601EQ';

    public function label(): string
    {
        return match ($this) {
            self::Vat2550Q => '2550Q — Quarterly VAT Return',
            self::Ewt1601EQ => '1601-EQ — Quarterly Expanded Withholding',
        };
    }

    /** The figure key that headlines this return (the amount due). */
    public function headlineKey(): string
    {
        return match ($this) {
            self::Vat2550Q => 'vat_payable',
            self::Ewt1601EQ => 'total_ewt',
        };
    }
}

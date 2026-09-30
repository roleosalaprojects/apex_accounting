<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxReturnType: string
{
    case Vat2550Q = '2550Q';
    case Ewt0619E = '0619E';
    case Ewt1601EQ = '1601EQ';
    case Ewt1604E = '1604E';
    case Pct2551Q = '2551Q';
    case IncomeTax1702Q = '1702Q';
    case IncomeTax1701Q = '1701Q';

    public function label(): string
    {
        return match ($this) {
            self::Vat2550Q => '2550Q — Quarterly VAT Return',
            self::Ewt0619E => '0619-E — Monthly Expanded Withholding Remittance',
            self::Ewt1601EQ => '1601-EQ — Quarterly Expanded Withholding',
            self::Ewt1604E => '1604-E — Annual Information Return (Expanded Withholding)',
            self::Pct2551Q => '2551Q — Quarterly Percentage Tax',
            self::IncomeTax1702Q => '1702Q — Quarterly Income Tax (corporate)',
            self::IncomeTax1701Q => '1701Q — Quarterly Income Tax (individual)',
        };
    }

    /** The BIR form number alone, for badges: "0619-E". */
    public function form(): string
    {
        return explode(' — ', $this->label(), 2)[0];
    }

    /** What the form is for: "Monthly Expanded Withholding Remittance". */
    public function title(): string
    {
        return explode(' — ', $this->label(), 2)[1] ?? $this->label();
    }

    /** The figure key that headlines this return (the amount due). */
    public function headlineKey(): string
    {
        return match ($this) {
            self::Vat2550Q => 'vat_payable',
            self::Ewt0619E, self::Ewt1604E => 'total_ewt',
            self::Ewt1601EQ, self::Pct2551Q, self::IncomeTax1702Q, self::IncomeTax1701Q => 'tax_due',
        };
    }

    /** How long a period the return covers: 'month', 'quarter' or 'year'. */
    public function period(): string
    {
        return match ($this) {
            self::Ewt0619E => 'month',
            self::Ewt1604E => 'year',
            default => 'quarter',
        };
    }
}

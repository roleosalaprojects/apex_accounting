<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\TaxpayerType;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Models\TaxCode;

/**
 * Enforces the VAT12 usage gates that protect rice/POS segregation (§16.4):
 *  - a `non_vat` company may never use VAT12 on any document (§4.1);
 *  - a VAT-exempt item/line may never carry VAT12 (§5.5, §9).
 *
 * Percentage-tax computation for non-VAT taxpayers is v2 (§19); only the gate
 * lives in v1.
 */
final class TaxValidator
{
    public function assertAllowed(TaxCode $taxCode, TaxpayerType $taxpayerType, bool $lineIsVatExempt = false): void
    {
        if (! $taxCode->isVat12()) {
            return;
        }

        if ($taxpayerType === TaxpayerType::NonVat) {
            throw InvalidVatBucketException::make('a non-VAT company cannot use VAT12');
        }

        if ($lineIsVatExempt) {
            throw InvalidVatBucketException::make('a VAT-exempt item cannot carry VAT12');
        }
    }
}

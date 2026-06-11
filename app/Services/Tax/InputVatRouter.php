<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\VatBucket;

/**
 * Input VAT three-bucket attribution (§5.3). Decides which account, if any,
 * receives the input VAT of a purchase line:
 *
 *  - direct_vatable -> Dr 1400 Input VAT          (100% creditable)
 *  - common         -> Dr 1410 Deferred Common    (allocated quarterly)
 *  - direct_exempt  -> null: not creditable; the VAT is capitalized into the
 *                      expense/inventory cost line instead (§16.5)
 */
final class InputVatRouter
{
    public const INPUT_VAT_ACCOUNT = '1400';

    public const DEFERRED_COMMON_ACCOUNT = '1410';

    /**
     * The account code to debit for this line's input VAT, or null when the VAT
     * must be folded into cost (direct_exempt).
     */
    public function accountCodeFor(VatBucket $bucket): ?string
    {
        return match ($bucket) {
            VatBucket::DirectVatable => self::INPUT_VAT_ACCOUNT,
            VatBucket::Common => self::DEFERRED_COMMON_ACCOUNT,
            VatBucket::DirectExempt => null,
        };
    }

    public function isCreditableNow(VatBucket $bucket): bool
    {
        return $bucket === VatBucket::DirectVatable;
    }

    public function isCapitalizedIntoCost(VatBucket $bucket): bool
    {
        return $bucket === VatBucket::DirectExempt;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountSubtype;

/**
 * Explicit subtype → cash-flow section map (§12.5). This is code, written once
 * and tested against the golden master — never inferred ad hoc per report run.
 * Cash/bank subtypes are the cash being explained and have no section.
 */
final class CashFlowMap
{
    public const OPERATING = 'operating';

    public const INVESTING = 'investing';

    public const FINANCING = 'financing';

    public function sectionFor(AccountSubtype $subtype): ?string
    {
        return match ($subtype) {
            AccountSubtype::Cash, AccountSubtype::Bank => null,

            AccountSubtype::AccountsReceivable, AccountSubtype::Inventory,
            AccountSubtype::OtherCurrentAsset, AccountSubtype::AccountsPayable,
            AccountSubtype::CreditCard, AccountSubtype::VatPayable,
            AccountSubtype::WithholdingPayable, AccountSubtype::OtherCurrentLiability,
            AccountSubtype::Income, AccountSubtype::OtherIncome, AccountSubtype::Cogs,
            AccountSubtype::Expense, AccountSubtype::DepreciationExpense,
            AccountSubtype::OtherExpense => self::OPERATING,

            AccountSubtype::FixedAsset, AccountSubtype::AccumulatedDepreciation,
            AccountSubtype::OtherAsset => self::INVESTING,

            AccountSubtype::LongTermLiability, AccountSubtype::Equity,
            AccountSubtype::RetainedEarnings => self::FINANCING,
        };
    }
}

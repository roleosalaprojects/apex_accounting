<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountSubtype: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case AccountsReceivable = 'accounts_receivable';
    case Inventory = 'inventory';
    case OtherCurrentAsset = 'other_current_asset';
    case FixedAsset = 'fixed_asset';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case OtherAsset = 'other_asset';
    case AccountsPayable = 'accounts_payable';
    case CreditCard = 'credit_card';
    case VatPayable = 'vat_payable';
    case WithholdingPayable = 'withholding_payable';
    case OtherCurrentLiability = 'other_current_liability';
    case LongTermLiability = 'long_term_liability';
    case Equity = 'equity';
    case RetainedEarnings = 'retained_earnings';
    case Income = 'income';
    case OtherIncome = 'other_income';
    case Cogs = 'cogs';
    case Expense = 'expense';
    case DepreciationExpense = 'depreciation_expense';
    case OtherExpense = 'other_expense';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash, self::Bank, self::AccountsReceivable, self::Inventory,
            self::OtherCurrentAsset, self::FixedAsset, self::AccumulatedDepreciation,
            self::OtherAsset => AccountType::Asset,

            self::AccountsPayable, self::CreditCard, self::VatPayable,
            self::WithholdingPayable, self::OtherCurrentLiability,
            self::LongTermLiability => AccountType::Liability,

            self::Equity, self::RetainedEarnings => AccountType::Equity,

            self::Income, self::OtherIncome => AccountType::Income,

            self::Cogs, self::Expense, self::DepreciationExpense,
            self::OtherExpense => AccountType::Expense,
        };
    }

    public function normalBalance(): NormalBalance
    {
        // Accumulated depreciation is a contra-asset: credit normal balance.
        if ($this === self::AccumulatedDepreciation) {
            return NormalBalance::Credit;
        }

        return $this->type()->normalBalance();
    }
}

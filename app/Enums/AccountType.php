<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::Asset, self::Expense => NormalBalance::Debit,
            self::Liability, self::Equity, self::Income => NormalBalance::Credit,
        };
    }

    /**
     * Nominal (temporary) accounts close into Retained Earnings at year-end.
     */
    public function isNominal(): bool
    {
        return $this === self::Income || $this === self::Expense;
    }
}

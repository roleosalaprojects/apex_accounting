<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountType;
use App\Models\Account;

/**
 * Profit & Loss for a period (§12.3). Income and expense movement over the
 * range; net income = income − expense.
 */
final class ProfitAndLossReport
{
    public function __construct(private readonly ReportBalances $balances) {}

    /**
     * @return array{income: array<int, array<string, mixed>>, expense: array<int, array<string, mixed>>, total_income: int, total_expense: int, net_income: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $movement = $this->balances->movementBetween($companyId, $from, $asOf);

        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->get()->keyBy('id');

        $income = [];
        $expense = [];
        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($movement as $accountId => $m) {
            $account = $accounts->get($accountId);
            if ($account === null) {
                continue;
            }

            if ($account->type === AccountType::Income) {
                $amount = $m['credit'] - $m['debit']; // income is credit-normal
                if ($amount === 0) {
                    continue;
                }
                $totalIncome += $amount;
                $income[] = $this->line($account, $amount);
            } elseif ($account->type === AccountType::Expense) {
                $amount = $m['debit'] - $m['credit']; // expense is debit-normal
                if ($amount === 0) {
                    continue;
                }
                $totalExpense += $amount;
                $expense[] = $this->line($account, $amount);
            }
        }

        usort($income, fn ($a, $b) => $a['code'] <=> $b['code']);
        usort($expense, fn ($a, $b) => $a['code'] <=> $b['code']);

        return [
            'income' => $income,
            'expense' => $expense,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_income' => $totalIncome - $totalExpense,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Account $account, int $amount): array
    {
        return [
            'account_id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'subtype' => $account->subtype->value,
            'amount' => $amount,
        ];
    }
}

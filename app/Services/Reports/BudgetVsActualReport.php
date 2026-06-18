<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\Budget;

/**
 * Budget vs Actual (§12): each budget line's annual target against the ledger
 * actual for the selected range. Actuals are taken in the account's natural
 * direction so they compare like-for-like with the (positive) budget figure.
 */
final class BudgetVsActualReport
{
    public function __construct(private readonly ReportBalances $balances) {}

    /**
     * @return array{rows: list<array<string, mixed>>, total_budget: int, total_actual: int, total_variance: int}
     */
    public function build(int $companyId, int $budgetId, string $from, string $asOf): array
    {
        $budget = Budget::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with('lines')
            ->findOrFail($budgetId);

        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->get()->keyBy('id');

        $movement = $this->balances->movementBetween($companyId, $from, $asOf);

        $rows = [];
        $totalBudget = 0;
        $totalActual = 0;

        foreach ($budget->lines as $line) {
            $account = $accounts->get($line->account_id);
            if ($account === null) {
                continue;
            }

            $m = $movement[$line->account_id] ?? ['debit' => 0, 'credit' => 0];
            $actual = $account->type->normalBalance() === NormalBalance::Debit
                ? $m['debit'] - $m['credit']
                : $m['credit'] - $m['debit'];

            $budgetAmount = (int) $line->amount;

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'budget' => $budgetAmount,
                'actual' => $actual,
                'variance' => $actual - $budgetAmount,
                'pct' => $budgetAmount !== 0 ? round($actual / $budgetAmount * 100, 1) : null,
            ];

            $totalBudget += $budgetAmount;
            $totalActual += $actual;
        }

        usort($rows, fn (array $a, array $b): int => $a['code'] <=> $b['code']);

        return [
            'rows' => $rows,
            'total_budget' => $totalBudget,
            'total_actual' => $totalActual,
            'total_variance' => $totalActual - $totalBudget,
        ];
    }
}

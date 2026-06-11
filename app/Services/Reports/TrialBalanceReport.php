<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Account;

/**
 * Trial Balance as of a date (§12.1). Ending signed balances split into debit
 * and credit columns; totals must tie out (§12).
 */
final class TrialBalanceReport
{
    public function __construct(private readonly ReportBalances $balances) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, total_debit: int, total_credit: int, balanced: bool}
     */
    public function build(int $companyId, string $asOf): array
    {
        $perAccount = $this->balances->perAccount($companyId, null, $asOf);

        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->get()->keyBy('id');

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($perAccount as $row) {
            $ending = $row['ending'];
            if ($ending === 0) {
                continue;
            }

            $account = $accounts->get($row['account_id']);
            $debit = $ending > 0 ? $ending : 0;
            $credit = $ending < 0 ? -$ending : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'account_id' => $row['account_id'],
                'code' => $account?->code,
                'name' => $account?->name,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        usort($rows, fn ($a, $b) => ($a['code'] ?? '') <=> ($b['code'] ?? ''));

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
        ];
    }
}

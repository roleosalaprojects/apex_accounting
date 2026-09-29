<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ledger figures for the dashboard, read like every report (posted and
 * reversed entries). Monthly series are grouped by day in SQL and bucketed
 * into months in PHP, so the queries stay portable across databases.
 */
final class DashboardMetrics
{
    private const EFFECTIVE = [JournalStatus::Posted->value, JournalStatus::Reversed->value];

    /**
     * Income, expenses (cost of sales included) and net income for each of the
     * $months months ending with the month of $end.
     *
     * @return list<array{month: CarbonImmutable, income: int, expenses: int, net: int}>
     */
    public function monthlyProfitAndLoss(int $companyId, CarbonImmutable $end, int $months = 12): array
    {
        $series = [];
        foreach ($this->months($end, $months) as $key => $month) {
            $series[$key] = ['month' => $month, 'income' => 0, 'expenses' => 0, 'net' => 0];
        }

        $rows = $this->lines($companyId)
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->whereIn('accounts.type', [AccountType::Income->value, AccountType::Expense->value])
            ->whereDate('journal_entries.entry_date', '>=', array_values($series)[0]['month']->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->groupBy('journal_entries.entry_date', 'accounts.type')
            ->selectRaw('journal_entries.entry_date as day, accounts.type as type, SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c')
            ->get();

        foreach ($rows as $row) {
            $key = substr((string) $row->day, 0, 7);
            if (! isset($series[$key])) {
                continue;
            }

            if ($row->type === AccountType::Income->value) {
                $series[$key]['income'] += (int) $row->c - (int) $row->d;
            } else {
                $series[$key]['expenses'] += (int) $row->d - (int) $row->c;
            }
        }

        foreach ($series as $key => $point) {
            $series[$key]['net'] = $point['income'] - $point['expenses'];
        }

        return array_values($series);
    }

    /**
     * Debit-positive balance of the accounts with the given subtypes at the end
     * of each of the $months months ending with the month of $end (the last
     * point is the balance at $end itself).
     *
     * @param  list<AccountSubtype>  $subtypes
     * @return list<array{month: CarbonImmutable, balance: int}>
     */
    public function monthlyBalances(int $companyId, array $subtypes, CarbonImmutable $end, int $months = 12): array
    {
        $accountIds = $this->accountIds($companyId, $subtypes);
        $monthsList = $this->months($end, $months);
        $first = reset($monthsList);

        $movement = [];
        if ($accountIds !== []) {
            $rows = $this->lines($companyId)
                ->whereIn('journal_lines.account_id', $accountIds)
                ->whereDate('journal_entries.entry_date', '>=', $first->toDateString())
                ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
                ->groupBy('journal_entries.entry_date')
                ->selectRaw('journal_entries.entry_date as day, SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
                ->get();

            foreach ($rows as $row) {
                $key = substr((string) $row->day, 0, 7);
                $movement[$key] = ($movement[$key] ?? 0) + (int) $row->net;
            }
        }

        $running = $this->balanceOf($companyId, $subtypes, $first->subDay()->toDateString());
        $series = [];
        foreach ($monthsList as $key => $month) {
            $running += $movement[$key] ?? 0;
            $series[] = ['month' => $month, 'balance' => $running];
        }

        return $series;
    }

    /**
     * Debit-positive balance of the accounts with the given subtypes as of a date.
     *
     * @param  list<AccountSubtype>  $subtypes
     */
    public function balanceOf(int $companyId, array $subtypes, string $asOf): int
    {
        $accountIds = $this->accountIds($companyId, $subtypes);

        return $accountIds === [] ? 0 : $this->balanceOfAccounts($companyId, $accountIds, $asOf);
    }

    /** Credit-positive balance of one account (by code) as of a date. */
    public function creditBalanceOfCode(int $companyId, string $code, string $asOf): int
    {
        $accountId = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', $code)->value('id');

        return $accountId === null ? 0 : -$this->balanceOfAccounts($companyId, [(int) $accountId], $asOf);
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function balanceOfAccounts(int $companyId, array $accountIds, string $asOf): int
    {
        return (int) $this->lines($companyId)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));
    }

    /**
     * @param  list<AccountSubtype>  $subtypes
     * @return list<int>
     */
    private function accountIds(int $companyId, array $subtypes): array
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('subtype', array_map(fn (AccountSubtype $subtype): string => $subtype->value, $subtypes))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, CarbonImmutable> 'Y-m' => first day of the month, oldest first
     */
    private function months(CarbonImmutable $end, int $count): array
    {
        $months = [];
        $month = $end->startOfMonth()->subMonthsNoOverflow($count - 1);
        for ($i = 0; $i < $count; $i++) {
            $months[$month->format('Y-m')] = $month;
            $month = $month->addMonthNoOverflow();
        }

        return $months;
    }

    private function lines(int $companyId): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', self::EFFECTIVE);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Enums\JournalStatus;
use App\Models\AccountingPeriod;
use App\Models\PeriodBalance;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for period_balances. Recomputes opening/debits/
 * credits/closing per (account, period) directly from journal_lines, carrying
 * the signed closing forward across periods (opening of N = closing of N-1).
 *
 * Signed convention: closing = opening + debits - credits, so debit-normal
 * accounts carry a positive balance and credit-normal accounts negative.
 *
 * Posted AND reversed entries both have real GL lines (a reversal adds an
 * offsetting entry rather than erasing the original), so both count.
 */
final class LedgerBalanceCalculator
{
    private const EFFECTIVE_STATUSES = [
        JournalStatus::Posted->value,
        JournalStatus::Reversed->value,
    ];

    /**
     * Recompute and persist period_balances for specific accounts (used by the
     * posting engine for the accounts a journal entry touched).
     *
     * @param  array<int, int>  $accountIds
     */
    public function persistForAccounts(int $companyId, array $accountIds): void
    {
        $accountIds = array_values(array_unique($accountIds));

        if ($accountIds === []) {
            return;
        }

        $rows = $this->compute($companyId, $accountIds);

        PeriodBalance::query()
            ->where('company_id', $companyId)
            ->whereIn('account_id', $accountIds)
            ->delete();

        if ($rows !== []) {
            PeriodBalance::query()->insert($rows);
        }
    }

    /**
     * Recompute and persist period_balances for every account of a company.
     */
    public function persistAll(int $companyId): void
    {
        $rows = $this->compute($companyId);

        PeriodBalance::query()->where('company_id', $companyId)->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            PeriodBalance::query()->insert($chunk);
        }
    }

    /**
     * Compute the full set of period_balances rows for a company (or a subset of
     * accounts) without persisting. Used by ledger:verify to compare against the
     * stored rows.
     *
     * @param  array<int, int>|null  $accountIds
     * @return array<int, array<string, int>>
     */
    public function compute(int $companyId, ?array $accountIds = null): array
    {
        $periods = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('fiscal_year')
            ->orderBy('period_no')
            ->get(['id']);

        if ($periods->isEmpty()) {
            return [];
        }

        $orderedPeriodIds = $periods->pluck('id')->all();

        $movementQuery = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', self::EFFECTIVE_STATUSES)
            ->groupBy('journal_lines.account_id', 'journal_entries.period_id')
            ->select(
                'journal_lines.account_id',
                'journal_entries.period_id',
                DB::raw('SUM(journal_lines.debit) as debits'),
                DB::raw('SUM(journal_lines.credit) as credits'),
            );

        if ($accountIds !== null) {
            $movementQuery->whereIn('journal_lines.account_id', $accountIds);
        }

        /** @var array<int, array<int, array{debits: int, credits: int}>> $movements */
        $movements = [];
        foreach ($movementQuery->get() as $row) {
            $movements[(int) $row->account_id][(int) $row->period_id] = [
                'debits' => (int) $row->debits,
                'credits' => (int) $row->credits,
            ];
        }

        $accounts = $accountIds ?? array_keys($movements);

        $rows = [];
        foreach ($accounts as $accountId) {
            $running = 0;
            foreach ($orderedPeriodIds as $periodId) {
                $movement = $movements[$accountId][$periodId] ?? ['debits' => 0, 'credits' => 0];
                $opening = $running;
                $debits = $movement['debits'];
                $credits = $movement['credits'];
                $closing = $opening + $debits - $credits;
                $running = $closing;

                if ($opening === 0 && $debits === 0 && $credits === 0 && $closing === 0) {
                    continue;
                }

                $rows[] = [
                    'company_id' => $companyId,
                    'period_id' => $periodId,
                    'account_id' => (int) $accountId,
                    'opening' => $opening,
                    'debits' => $debits,
                    'credits' => $credits,
                    'closing' => $closing,
                ];
            }
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\JournalStatus;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Key figures for an account's page: the balance as of a date, the debits and
 * credits posted in the period, and the date of the latest entry. Balances are
 * signed debit-positive, as in ReportBalances (posted and reversed entries).
 */
final class AccountSummary
{
    /**
     * @return array{balance: int, debits: int, credits: int, last_entry: string|null}
     */
    public function build(Account $account, string $from, string $asOf): array
    {
        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '<=', $asOf);

        $total = (clone $lines)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
            ->value('balance');

        $lastEntry = (clone $lines)->max('journal_entries.entry_date');

        $period = $lines->whereDate('journal_entries.entry_date', '>=', $from)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as d, COALESCE(SUM(journal_lines.credit), 0) as c')
            ->first();

        return [
            'balance' => (int) $total,
            'debits' => (int) ($period->d ?? 0),
            'credits' => (int) ($period->c ?? 0),
            'last_entry' => $lastEntry === null ? null : substr((string) $lastEntry, 0, 10),
        ];
    }
}

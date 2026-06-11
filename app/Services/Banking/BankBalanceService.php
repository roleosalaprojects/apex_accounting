<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Enums\JournalStatus;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Bank/cash balances are ALWAYS derived from the GL, never stored (§16.9).
 * Signed (debit-positive) balance of the linked GL account.
 */
final class BankBalanceService
{
    public function currentBalance(BankAccount $bankAccount, ?string $asOf = null): int
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $bankAccount->company_id)
            ->where('journal_lines.account_id', $bankAccount->account_id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value]);

        if ($asOf !== null) {
            $query->whereDate('journal_entries.entry_date', '<=', $asOf);
        }

        $row = $query->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as bal')->first();

        return (int) ($row->bal ?? 0);
    }

    /**
     * Cleared balance for a reconciliation = sum of signed amounts of its cleared lines.
     */
    public function clearedBalance(int $reconciliationId): int
    {
        $row = DB::table('reconciliation_items')
            ->join('journal_lines', 'reconciliation_items.journal_line_id', '=', 'journal_lines.id')
            ->where('reconciliation_items.reconciliation_id', $reconciliationId)
            ->where('reconciliation_items.is_cleared', true)
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as bal')
            ->first();

        return (int) ($row->bal ?? 0);
    }
}

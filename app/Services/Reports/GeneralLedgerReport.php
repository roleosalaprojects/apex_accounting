<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\JournalStatus;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger — per-account line detail with a running balance (§12.2).
 * Drill-down reads journal_lines.
 */
final class GeneralLedgerReport
{
    public function __construct(private readonly ReportBalances $balances) {}

    /**
     * @return array{opening: int, rows: array<int, array<string, mixed>>, ending: int}
     */
    public function build(int $companyId, int $accountId, string $from, string $asOf): array
    {
        $opening = $this->balances->signedBalanceBefore($companyId, $from)[$accountId] ?? 0;

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_lines.account_id', $accountId)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->selectRaw('journal_entries.entry_date, journal_entries.number, journal_lines.memo, journal_lines.debit, journal_lines.credit')
            ->get();

        $running = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $running += (int) $line->debit - (int) $line->credit;
            $rows[] = [
                'date' => $line->entry_date,
                'number' => $line->number,
                'memo' => $line->memo,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $running,
            ];
        }

        return ['opening' => $opening, 'rows' => $rows, 'ending' => $running];
    }
}

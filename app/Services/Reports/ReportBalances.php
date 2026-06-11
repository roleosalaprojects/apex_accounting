<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\JournalStatus;
use Illuminate\Support\Facades\DB;

/**
 * Shared ledger math for report builders: signed (debit-positive) opening,
 * period debit/credit movement, and ending balance per account over a date
 * range. Reads journal_lines (posted + reversed entries).
 */
final class ReportBalances
{
    private const EFFECTIVE = [JournalStatus::Posted->value, JournalStatus::Reversed->value];

    /**
     * @return array<int, array{account_id: int, opening: int, debit: int, credit: int, ending: int}>
     */
    public function perAccount(int $companyId, ?string $from, string $asOf): array
    {
        $opening = $from !== null ? $this->signedBalanceBefore($companyId, $from) : [];
        $movement = $this->movementBetween($companyId, $from, $asOf);

        $accountIds = array_unique([...array_keys($opening), ...array_keys($movement)]);

        $rows = [];
        foreach ($accountIds as $accountId) {
            $open = $opening[$accountId] ?? 0;
            $debit = $movement[$accountId]['debit'] ?? 0;
            $credit = $movement[$accountId]['credit'] ?? 0;
            $rows[$accountId] = [
                'account_id' => $accountId,
                'opening' => $open,
                'debit' => $debit,
                'credit' => $credit,
                'ending' => $open + $debit - $credit,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, int> account_id => signed balance
     */
    public function signedBalanceBefore(int $companyId, string $date): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', self::EFFECTIVE)
            ->whereDate('journal_entries.entry_date', '<', $date)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) as bal')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->account_id] = (int) $row->bal;
        }

        return $out;
    }

    /**
     * @return array<int, array{debit: int, credit: int}>
     */
    public function movementBetween(int $companyId, ?string $from, string $asOf): array
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', self::EFFECTIVE)
            ->whereDate('journal_entries.entry_date', '<=', $asOf);

        if ($from !== null) {
            $query->whereDate('journal_entries.entry_date', '>=', $from);
        }

        $rows = $query->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->account_id] = ['debit' => (int) $row->d, 'credit' => (int) $row->c];
        }

        return $out;
    }
}

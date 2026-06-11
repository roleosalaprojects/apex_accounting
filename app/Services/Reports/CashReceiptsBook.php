<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountSubtype;
use App\Enums\JournalStatus;
use App\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

/**
 * Cash Receipts Book (§12.9): inflows to cash/bank from collections. Manual JEs
 * (e.g. capital injections) belong to the General Journal, not the CRB.
 */
final class CashReceiptsBook
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.source_type', (new CustomerPayment)->getMorphClass())
            ->whereIn('accounts.subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->orderBy('journal_entries.entry_date')
            ->selectRaw('journal_entries.entry_date, journal_entries.number, journal_entries.memo, journal_lines.debit as amount')
            ->get();

        $total = 0;
        $out = [];
        foreach ($rows as $row) {
            $total += (int) $row->amount;
            $out[] = [
                'date' => $row->entry_date,
                'reference' => $row->number,
                'particulars' => $row->memo,
                'amount' => (int) $row->amount,
            ];
        }

        return ['rows' => $out, 'total' => $total];
    }
}

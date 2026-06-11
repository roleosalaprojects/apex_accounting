<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountSubtype;
use App\Enums\JournalStatus;
use App\Models\VendorPayment;
use Illuminate\Support\Facades\DB;

/**
 * Cash Disbursements Book (§12.10): outflows from cash/bank to vendor payments,
 * with voucher numbers shown.
 */
final class CashDisbursementsBook
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->leftJoin('vendor_payments', 'journal_entries.source_id', '=', 'vendor_payments.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.source_type', (new VendorPayment)->getMorphClass())
            ->whereIn('accounts.subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->orderBy('journal_entries.entry_date')
            ->selectRaw('journal_entries.entry_date, journal_entries.number, vendor_payments.voucher_no, journal_entries.memo, journal_lines.credit as amount')
            ->get();

        $total = 0;
        $out = [];
        foreach ($rows as $row) {
            $total += (int) $row->amount;
            $out[] = [
                'date' => $row->entry_date,
                'reference' => $row->number,
                'voucher_no' => $row->voucher_no,
                'particulars' => $row->memo,
                'amount' => (int) $row->amount,
            ];
        }

        return ['rows' => $out, 'total' => $total];
    }
}

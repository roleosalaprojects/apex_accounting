<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Key figures for a vendor's page: what is owed to them and how much of it is
 * past due (open bills less the payments and debit memos applied, as on the AP
 * aging, counting only those dated by the as-of date), what they billed and
 * how much EWT was withheld from them in the period, and the last payment made
 * to them.
 */
final class VendorSummary
{
    /**
     * @return array{balance: int, overdue: int, open_bills: int, overdue_bills: int, billed: int, ewt: int, last_payment: array{date: string, amount: int}|null}
     */
    public function build(Vendor $vendor, string $from, string $asOf): array
    {
        $open = DB::table('bills')
            ->where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
            ->whereDate('bill_date', '<=', $asOf)
            ->selectRaw('COALESCE(due_date, bill_date) as due, total
                - COALESCE((SELECT SUM(ba.amount) FROM bill_applications ba
                    JOIN vendor_payments vp ON vp.id = ba.vendor_payment_id
                    WHERE ba.bill_id = bills.id AND DATE(vp.payment_date) <= ?), 0)
                - COALESCE((SELECT SUM(da.amount) FROM debit_memo_applications da
                    JOIN debit_memos dm ON dm.id = da.debit_memo_id
                    WHERE da.bill_id = bills.id AND DATE(dm.memo_date) <= ?), 0) as outstanding', [$asOf, $asOf])
            ->get()
            ->filter(fn (stdClass $row): bool => (int) $row->outstanding > 0);

        $overdue = $open->filter(fn (stdClass $row): bool => substr((string) $row->due, 0, 10) < $asOf);

        $billed = (int) DB::table('bills')
            ->where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->where('is_opening', false)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $asOf)
            ->sum('total');

        $payments = DB::table('vendor_payments')
            ->where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->whereDate('payment_date', '<=', $asOf);

        $ewt = (int) (clone $payments)->whereDate('payment_date', '>=', $from)->sum('ewt');

        $lastPayment = $payments->orderByDesc('payment_date')->orderByDesc('id')->first(['payment_date', 'net_paid']);

        return [
            'balance' => (int) $open->sum(fn (stdClass $row): int => (int) $row->outstanding),
            'overdue' => (int) $overdue->sum(fn (stdClass $row): int => (int) $row->outstanding),
            'open_bills' => $open->count(),
            'overdue_bills' => $overdue->count(),
            'billed' => $billed,
            'ewt' => $ewt,
            'last_payment' => $lastPayment === null ? null : [
                'date' => substr((string) $lastPayment->payment_date, 0, 10),
                'amount' => (int) $lastPayment->net_paid,
            ],
        ];
    }
}

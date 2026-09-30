<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Vendor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statement per vendor (§12.16): opening balance, the period's bills, the
 * payments and debit memos applied, and closing balance. Drill-down of the
 * AP control account by partner; the basis of the printed vendor statement.
 */
final class VendorStatement
{
    private const POSTED = [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value];

    /**
     * @return array{vendor: string, opening: int, rows: array<int, array<string, mixed>>, closing: int}
     */
    public function build(Vendor $vendor, string $from, string $asOf): array
    {
        $opening = $this->balanceBefore($vendor, $from);

        $bills = $this->bills($vendor)
            ->whereDate('bill_date', '>=', $from)->whereDate('bill_date', '<=', $asOf)
            ->selectRaw("bill_date as date, number, 'Bill' as type, total as charge, 0 as credit")->get();

        $payments = $this->paymentApplications($vendor)
            ->whereDate('vendor_payments.payment_date', '>=', $from)
            ->whereDate('vendor_payments.payment_date', '<=', $asOf)
            ->selectRaw("vendor_payments.payment_date as date, vendor_payments.number, 'Payment' as type, 0 as charge, bill_applications.amount as credit")->get();

        $debitMemos = $this->debitMemoApplications($vendor)
            ->whereDate('debit_memos.memo_date', '>=', $from)
            ->whereDate('debit_memos.memo_date', '<=', $asOf)
            ->selectRaw("debit_memos.memo_date as date, debit_memos.number, 'Debit memo' as type, 0 as charge, debit_memo_applications.amount as credit")->get();

        $entries = $bills->concat($payments)->concat($debitMemos)->sortBy('date')->values();

        $running = $opening;
        $rows = [];
        foreach ($entries as $entry) {
            $running += (int) $entry->charge - (int) $entry->credit;
            $rows[] = [
                'date' => substr((string) $entry->date, 0, 10),
                'number' => $entry->number,
                'type' => $entry->type,
                'charge' => (int) $entry->charge,
                'credit' => (int) $entry->credit,
                'balance' => $running,
            ];
        }

        return ['vendor' => $vendor->name, 'opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    private function balanceBefore(Vendor $vendor, string $from): int
    {
        $charges = (int) $this->bills($vendor)->whereDate('bill_date', '<', $from)->sum('total');
        $paid = (int) $this->paymentApplications($vendor)->whereDate('vendor_payments.payment_date', '<', $from)->sum('bill_applications.amount');
        $credited = (int) $this->debitMemoApplications($vendor)->whereDate('debit_memos.memo_date', '<', $from)->sum('debit_memo_applications.amount');

        return $charges - $paid - $credited;
    }

    private function bills(Vendor $vendor): Builder
    {
        return DB::table('bills')
            ->where('company_id', $vendor->company_id)->where('vendor_id', $vendor->id)
            ->whereIn('status', self::POSTED);
    }

    private function paymentApplications(Vendor $vendor): Builder
    {
        return DB::table('bill_applications')
            ->join('vendor_payments', 'bill_applications.vendor_payment_id', '=', 'vendor_payments.id')
            ->where('vendor_payments.company_id', $vendor->company_id)
            ->where('vendor_payments.vendor_id', $vendor->id)
            ->where('vendor_payments.status', 'posted');
    }

    private function debitMemoApplications(Vendor $vendor): Builder
    {
        return DB::table('debit_memo_applications')
            ->join('debit_memos', 'debit_memo_applications.debit_memo_id', '=', 'debit_memos.id')
            ->where('debit_memos.company_id', $vendor->company_id)
            ->where('debit_memos.vendor_id', $vendor->id)
            ->whereIn('debit_memos.status', ['posted', 'applied']);
    }
}

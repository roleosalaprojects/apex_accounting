<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Key figures for a customer's page: what they owe and how much of it is past
 * due (open invoices less the payments and credit memos applied, as on the AR
 * aging, counting only those dated by the as-of date), what was invoiced in
 * the period, and their last payment.
 */
final class CustomerSummary
{
    /**
     * @return array{balance: int, overdue: int, open_invoices: int, overdue_invoices: int, invoiced: int, last_payment: array{date: string, amount: int}|null}
     */
    public function build(Customer $customer, string $from, string $asOf): array
    {
        $open = DB::table('invoices')
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
            ->whereDate('invoice_date', '<=', $asOf)
            ->selectRaw('COALESCE(due_date, invoice_date) as due, total
                - COALESCE((SELECT SUM(pa.amount) FROM payment_applications pa
                    JOIN customer_payments cp ON cp.id = pa.customer_payment_id
                    WHERE pa.invoice_id = invoices.id AND DATE(cp.payment_date) <= ?), 0)
                - COALESCE((SELECT SUM(ca.amount) FROM credit_memo_applications ca
                    JOIN credit_memos cm ON cm.id = ca.credit_memo_id
                    WHERE ca.invoice_id = invoices.id AND DATE(cm.memo_date) <= ?), 0) as outstanding', [$asOf, $asOf])
            ->get()
            ->filter(fn (stdClass $row): bool => (int) $row->outstanding > 0);

        $overdue = $open->filter(fn (stdClass $row): bool => substr((string) $row->due, 0, 10) < $asOf);

        $invoiced = (int) DB::table('invoices')
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('is_opening', false)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $asOf)
            ->sum('total');

        $lastPayment = DB::table('customer_payments')
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'posted')
            ->whereDate('payment_date', '<=', $asOf)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->first(['payment_date', 'amount']);

        return [
            'balance' => (int) $open->sum(fn (stdClass $row): int => (int) $row->outstanding),
            'overdue' => (int) $overdue->sum(fn (stdClass $row): int => (int) $row->outstanding),
            'open_invoices' => $open->count(),
            'overdue_invoices' => $overdue->count(),
            'invoiced' => $invoiced,
            'last_payment' => $lastPayment === null ? null : [
                'date' => substr((string) $lastPayment->payment_date, 0, 10),
                'amount' => (int) $lastPayment->amount,
            ],
        ];
    }
}

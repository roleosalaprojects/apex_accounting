<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statement of Account per customer (§12.16): opening balance, the period's
 * invoices, payments and credit memos applied, and closing balance. Drill-down
 * of the AR control account by partner; the basis of the printed statement.
 */
final class StatementOfAccount
{
    private const POSTED = [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value];

    /**
     * @return array{customer: string, opening: int, rows: array<int, array<string, mixed>>, closing: int}
     */
    public function build(Customer $customer, string $from, string $asOf): array
    {
        $opening = $this->balanceBefore($customer, $from);

        $invoices = $this->invoices($customer)
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $asOf)
            ->selectRaw("invoice_date as date, number, 'Invoice' as type, total as charge, 0 as credit")->get();

        $payments = $this->paymentApplications($customer)
            ->whereDate('customer_payments.payment_date', '>=', $from)
            ->whereDate('customer_payments.payment_date', '<=', $asOf)
            ->selectRaw("customer_payments.payment_date as date, customer_payments.number, 'Payment' as type, 0 as charge, payment_applications.amount as credit")->get();

        $creditMemos = $this->creditMemoApplications($customer)
            ->whereDate('credit_memos.memo_date', '>=', $from)
            ->whereDate('credit_memos.memo_date', '<=', $asOf)
            ->selectRaw("credit_memos.memo_date as date, credit_memos.number, 'Credit memo' as type, 0 as charge, credit_memo_applications.amount as credit")->get();

        $entries = $invoices->concat($payments)->concat($creditMemos)->sortBy('date')->values();

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

        return ['customer' => $customer->name, 'opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    private function balanceBefore(Customer $customer, string $from): int
    {
        $charges = (int) $this->invoices($customer)->whereDate('invoice_date', '<', $from)->sum('total');
        $paid = (int) $this->paymentApplications($customer)->whereDate('customer_payments.payment_date', '<', $from)->sum('payment_applications.amount');
        $credited = (int) $this->creditMemoApplications($customer)->whereDate('credit_memos.memo_date', '<', $from)->sum('credit_memo_applications.amount');

        return $charges - $paid - $credited;
    }

    private function invoices(Customer $customer): Builder
    {
        return DB::table('invoices')
            ->where('company_id', $customer->company_id)->where('customer_id', $customer->id)
            ->whereIn('status', self::POSTED);
    }

    private function paymentApplications(Customer $customer): Builder
    {
        return DB::table('payment_applications')
            ->join('customer_payments', 'payment_applications.customer_payment_id', '=', 'customer_payments.id')
            ->where('customer_payments.company_id', $customer->company_id)
            ->where('customer_payments.customer_id', $customer->id)
            ->where('customer_payments.status', 'posted');
    }

    private function creditMemoApplications(Customer $customer): Builder
    {
        return DB::table('credit_memo_applications')
            ->join('credit_memos', 'credit_memo_applications.credit_memo_id', '=', 'credit_memos.id')
            ->where('credit_memos.company_id', $customer->company_id)
            ->where('credit_memos.customer_id', $customer->id)
            ->whereIn('credit_memos.status', ['posted', 'applied']);
    }
}

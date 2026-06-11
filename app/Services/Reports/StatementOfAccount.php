<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Statement of Account per customer (§12.16): opening balance, the period's
 * invoices / credit memos / payments, and closing balance. Drill-down of the
 * AR control account by partner.
 */
final class StatementOfAccount
{
    /**
     * @return array{customer: string, opening: int, rows: array<int, array<string, mixed>>, closing: int}
     */
    public function build(Customer $customer, string $from, string $asOf): array
    {
        $opening = $this->partnerBalanceBefore($customer, $from);

        $invoices = DB::table('invoices')
            ->where('company_id', $customer->company_id)->where('customer_id', $customer->id)
            ->where('status', '!=', 'voided')
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $asOf)
            ->selectRaw("invoice_date as date, number, 'Invoice' as type, total as charge, 0 as credit")->get();

        $payments = DB::table('customer_payments')
            ->join('payment_applications', 'customer_payments.id', '=', 'payment_applications.customer_payment_id')
            ->where('customer_payments.company_id', $customer->company_id)
            ->where('customer_payments.customer_id', $customer->id)
            ->where('customer_payments.status', 'posted')
            ->whereDate('customer_payments.payment_date', '>=', $from)
            ->whereDate('customer_payments.payment_date', '<=', $asOf)
            ->selectRaw("customer_payments.payment_date as date, customer_payments.number, 'Payment' as type, 0 as charge, payment_applications.amount as credit")->get();

        $entries = $invoices->concat($payments)->sortBy('date')->values();

        $running = $opening;
        $rows = [];
        foreach ($entries as $entry) {
            $running += (int) $entry->charge - (int) $entry->credit;
            $rows[] = [
                'date' => $entry->date,
                'number' => $entry->number,
                'type' => $entry->type,
                'charge' => (int) $entry->charge,
                'credit' => (int) $entry->credit,
                'balance' => $running,
            ];
        }

        return ['customer' => $customer->name, 'opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    private function partnerBalanceBefore(Customer $customer, string $from): int
    {
        $charges = (int) DB::table('invoices')
            ->where('company_id', $customer->company_id)->where('customer_id', $customer->id)
            ->where('status', '!=', 'voided')->whereDate('invoice_date', '<', $from)->sum('total');

        $credits = (int) DB::table('payment_applications')
            ->join('customer_payments', 'payment_applications.customer_payment_id', '=', 'customer_payments.id')
            ->where('customer_payments.customer_id', $customer->id)
            ->where('customer_payments.status', 'posted')
            ->whereDate('customer_payments.payment_date', '<', $from)->sum('payment_applications.amount');

        return $charges - $credits;
    }
}

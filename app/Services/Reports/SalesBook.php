<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Sales Journal / Sales Book (§12.11): per invoice — exempt, zero-rated,
 * VATable sales, output VAT, total. VAT-exempt never mixed into VATable.
 */
final class SalesBook
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $asOf)
            ->with('customer')
            ->orderBy('invoice_date')->get();

        $rows = [];
        $totals = ['exempt' => 0, 'zero_rated' => 0, 'vatable' => 0, 'output_vat' => 0, 'total' => 0];

        foreach ($invoices as $invoice) {
            $rows[] = [
                'date' => $invoice->invoice_date->toDateString(),
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'tin' => $invoice->customer?->tin,
                'exempt' => $invoice->exempt_sales->minor,
                'zero_rated' => $invoice->zero_rated_sales->minor,
                'vatable' => $invoice->vatable_sales->minor,
                'output_vat' => $invoice->vat_amount->minor,
                'total' => $invoice->total->minor,
            ];
            $totals['exempt'] += $invoice->exempt_sales->minor;
            $totals['zero_rated'] += $invoice->zero_rated_sales->minor;
            $totals['vatable'] += $invoice->vatable_sales->minor;
            $totals['output_vat'] += $invoice->vat_amount->minor;
            $totals['total'] += $invoice->total->minor;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}

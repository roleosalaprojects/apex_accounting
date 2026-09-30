<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Enums\PosZReadingStatus;
use App\Models\CreditMemo;
use App\Models\Invoice;
use App\Models\PosZReading;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Sales Book (§12.11): every sale of the period, one row per document —
 * invoices, POS Z-readings whose entry has been posted, and credit memos as
 * negative rows for sales returns. The VAT return is built from its totals.
 */
final class SalesBook
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $rows = [
            ...$this->invoices($companyId, $from, $asOf),
            ...$this->posSales($companyId, $from, $asOf),
            ...$this->creditMemos($companyId, $from, $asOf),
        ];
        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['number']] <=> [$b['date'], $b['number']]);

        $totals = ['exempt' => 0, 'zero_rated' => 0, 'vatable' => 0, 'output_vat' => 0, 'total' => 0];
        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoices(int $companyId, string $from, string $asOf): array
    {
        return Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $asOf)
            ->with('customer')
            ->orderBy('invoice_date')->get()
            ->map(fn (Invoice $invoice): array => [
                'date' => $invoice->invoice_date->toDateString(),
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'tin' => $invoice->customer?->tin,
                'exempt' => $invoice->exempt_sales->minor,
                'zero_rated' => $invoice->zero_rated_sales->minor,
                'vatable' => $invoice->vatable_sales->minor,
                'output_vat' => $invoice->vat_amount->minor,
                'total' => $invoice->total->minor,
            ])->all();
    }

    /**
     * POS sales count once the imported reading's entry is posted, and leave
     * again when it is reversed. Posting replaces the draft the reading links
     * to, so the entry is found by its source; a reversal entry carries the
     * same source and is skipped.
     *
     * @return list<array<string, mixed>>
     */
    private function posSales(int $companyId, string $from, string $asOf): array
    {
        return PosZReading::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', PosZReadingStatus::Imported->value)
            ->whereExists(fn (QueryBuilder $entry) => $entry->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'pos_z_readings.id')
                ->where('journal_entries.source_type', 'pos.zreading')
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.status', JournalStatus::Posted->value)
                ->whereNull('journal_entries.reversal_of_id'))
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $asOf)
            ->orderBy('business_date')->get()
            ->map(fn (PosZReading $reading): array => [
                'date' => $reading->business_date->toDateString(),
                'number' => $reading->reference ?? "Z-{$reading->id}",
                'customer' => 'POS sales',
                'tin' => null,
                'exempt' => $reading->exempt_sales,
                'zero_rated' => $reading->zero_rated_sales,
                'vatable' => $reading->vatable_sales,
                'output_vat' => $reading->vat_amount,
                'total' => $reading->vatable_sales + $reading->vat_amount + $reading->exempt_sales + $reading->zero_rated_sales - $reading->discounts,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function creditMemos(int $companyId, string $from, string $asOf): array
    {
        return CreditMemo::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', ['posted', 'applied'])
            ->whereDate('memo_date', '>=', $from)
            ->whereDate('memo_date', '<=', $asOf)
            ->with('customer')
            ->orderBy('memo_date')->get()
            ->map(fn (CreditMemo $memo): array => [
                'date' => $memo->memo_date->toDateString(),
                'number' => $memo->number,
                'customer' => $memo->customer?->name,
                'tin' => $memo->customer?->tin,
                'exempt' => -$memo->exempt_sales->minor,
                'zero_rated' => -$memo->zero_rated_sales->minor,
                'vatable' => -$memo->vatable_sales->minor,
                'output_vat' => -$memo->vat_amount->minor,
                'total' => -$memo->total->minor,
            ])->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\VatBucket;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Journal / Purchase Book (§12.12): per bill — exempt purchases,
 * VATable purchases, input VAT by bucket, total.
 */
final class PurchaseBook
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $bills = Bill::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $asOf)
            ->with('vendor')
            ->orderBy('bill_date')->get();

        $rows = [];
        $totals = ['exempt' => 0, 'vatable' => 0, 'input_vat_direct' => 0, 'input_vat_common' => 0, 'total' => 0];

        foreach ($bills as $bill) {
            $buckets = $this->bucketVat($bill->id);

            $rows[] = [
                'date' => $bill->bill_date->toDateString(),
                'number' => $bill->number,
                'vendor' => $bill->vendor?->name,
                'tin' => $bill->vendor?->tin,
                'exempt' => $bill->exempt_purchases->minor,
                'vatable' => $bill->vatable_purchases->minor,
                'input_vat_direct' => $buckets['direct'],
                'input_vat_common' => $buckets['common'],
                'total' => $bill->total->minor,
            ];
            $totals['exempt'] += $bill->exempt_purchases->minor;
            $totals['vatable'] += $bill->vatable_purchases->minor;
            $totals['input_vat_direct'] += $buckets['direct'];
            $totals['input_vat_common'] += $buckets['common'];
            $totals['total'] += $bill->total->minor;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return array{direct: int, common: int}
     */
    private function bucketVat(int $billId): array
    {
        $rows = DB::table('bill_lines')
            ->where('bill_id', $billId)
            ->groupBy('vat_bucket')
            ->selectRaw('vat_bucket, SUM(vat_amount) as vat')
            ->pluck('vat', 'vat_bucket');

        return [
            'direct' => (int) ($rows[VatBucket::DirectVatable->value] ?? 0),
            'common' => (int) ($rows[VatBucket::Common->value] ?? 0),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Bill;
use Illuminate\Support\Carbon;

/**
 * AP aging (§12.6): current / 1–30 / 31–60 / 61–90 / 90+ on days past the bill
 * due date. Outstanding = total − applied.
 */
final class ApAgingReport
{
    public const BUCKETS = ['current', '1_30', '31_60', '61_90', '90_plus'];

    /**
     * @return array{buckets: array<string, int>, rows: array<int, array<string, mixed>>, total: int}
     */
    public function build(int $companyId, string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf);

        $bills = Bill::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('bill_date', '<=', $asOf)
            ->with(['applications', 'debitMemoApplications', 'vendor'])
            ->get();

        $buckets = array_fill_keys(self::BUCKETS, 0);
        $rows = [];
        $total = 0;

        foreach ($bills as $bill) {
            $outstanding = $bill->outstanding();
            if ($outstanding <= 0) {
                continue;
            }

            $due = $bill->due_date ?? $bill->bill_date;
            $bucket = $due->gte($asOfDate) ? 'current' : match (true) {
                $due->diffInDays($asOfDate) <= 30 => '1_30',
                $due->diffInDays($asOfDate) <= 60 => '31_60',
                $due->diffInDays($asOfDate) <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$bucket] += $outstanding;
            $total += $outstanding;
            $rows[] = [
                'bill_id' => $bill->id,
                'number' => $bill->number,
                'vendor' => $bill->vendor?->name,
                'due_date' => $due->toDateString(),
                'outstanding' => $outstanding,
                'bucket' => $bucket,
            ];
        }

        return ['buckets' => $buckets, 'rows' => $rows, 'total' => $total];
    }
}

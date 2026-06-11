<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * AR aging (§12.6): current / 1–30 / 31–60 / 61–90 / 90+ based on days past the
 * invoice due date as of a given date. Outstanding = total − applied.
 */
final class ArAgingReport
{
    public const BUCKETS = ['current', '1_30', '31_60', '61_90', '90_plus'];

    /**
     * @return array{buckets: array<string, int>, rows: array<int, array<string, mixed>>, total: int}
     */
    public function build(int $companyId, string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf);

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('invoice_date', '<=', $asOf)
            ->with(['paymentApplications', 'creditMemoApplications', 'customer'])
            ->get();

        $buckets = array_fill_keys(self::BUCKETS, 0);
        $rows = [];
        $total = 0;

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstanding();
            if ($outstanding <= 0) {
                continue;
            }

            $bucket = $this->bucketFor($invoice->due_date ?? $invoice->invoice_date, $asOfDate);
            $buckets[$bucket] += $outstanding;
            $total += $outstanding;

            $rows[] = [
                'invoice_id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'due_date' => ($invoice->due_date ?? $invoice->invoice_date)->toDateString(),
                'outstanding' => $outstanding,
                'bucket' => $bucket,
            ];
        }

        return ['buckets' => $buckets, 'rows' => $rows, 'total' => $total];
    }

    private function bucketFor(Carbon $dueDate, Carbon $asOf): string
    {
        if ($dueDate->gte($asOf)) {
            return 'current';
        }

        $daysPastDue = $dueDate->diffInDays($asOf);

        return match (true) {
            $daysPastDue <= 30 => '1_30',
            $daysPastDue <= 60 => '31_60',
            $daysPastDue <= 90 => '61_90',
            default => '90_plus',
        };
    }
}

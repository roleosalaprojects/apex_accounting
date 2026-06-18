<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * Dunning / collections (§12.6b): per-customer outstanding and past-due AR as of
 * a date, with credit-limit utilisation so over-limit customers stand out.
 */
final class DunningReport
{
    /**
     * @return array{rows: list<array<string, mixed>>, total_outstanding: int, total_overdue: int}
     */
    public function build(int $companyId, string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf);

        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('invoice_date', '<=', $asOf)
            ->with(['paymentApplications', 'creditMemoApplications', 'customer'])
            ->get();

        /** @var array<int, array{customer: ?string, credit_limit: int, outstanding: int, overdue: int, oldest_due: ?string}> $byCustomer */
        $byCustomer = [];

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstanding();
            if ($outstanding <= 0) {
                continue;
            }

            $due = $invoice->due_date ?? $invoice->invoice_date;
            $isOverdue = $due->lt($asOfDate);
            $cid = $invoice->customer_id;

            $byCustomer[$cid] ??= [
                'customer' => $invoice->customer?->name,
                'credit_limit' => (int) ($invoice->customer?->getRawOriginal('credit_limit') ?? 0),
                'outstanding' => 0,
                'overdue' => 0,
                'oldest_due' => null,
            ];

            $byCustomer[$cid]['outstanding'] += $outstanding;

            if ($isOverdue) {
                $byCustomer[$cid]['overdue'] += $outstanding;
                $dueStr = $due->toDateString();
                if ($byCustomer[$cid]['oldest_due'] === null || $dueStr < $byCustomer[$cid]['oldest_due']) {
                    $byCustomer[$cid]['oldest_due'] = $dueStr;
                }
            }
        }

        $rows = [];
        $totalOutstanding = 0;
        $totalOverdue = 0;
        foreach ($byCustomer as $c) {
            $limit = $c['credit_limit'];
            $rows[] = [
                'customer' => $c['customer'],
                'outstanding' => $c['outstanding'],
                'overdue' => $c['overdue'],
                'oldest_due' => $c['oldest_due'],
                'credit_limit' => $limit,
                'available' => $limit > 0 ? $limit - $c['outstanding'] : null,
                'over_limit' => $limit > 0 && $c['outstanding'] > $limit,
            ];
            $totalOutstanding += $c['outstanding'];
            $totalOverdue += $c['overdue'];
        }

        usort($rows, fn (array $a, array $b): int => $b['overdue'] <=> $a['overdue']);

        return ['rows' => $rows, 'total_outstanding' => $totalOutstanding, 'total_overdue' => $totalOverdue];
    }
}

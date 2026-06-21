<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\Invoice;
use App\Support\Currencies;
use Throwable;

/**
 * Unrealized FX revaluation working paper (§17): open foreign receivables and
 * payables restated from their booked (issue-rate) PHP to the period-end rate,
 * showing the unrealized gain/(loss). This is a working paper only — it does not
 * post, so it never disturbs the sub-ledger that realized settlement works from.
 */
final class FxRevaluationReport
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    /**
     * @return array{rows: list<array<string, mixed>>, total_unrealized: int}
     */
    public function build(int $companyId, string $asOf): array
    {
        $rows = [];
        $totalUnrealized = 0;

        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
            ->where('currency_code', '!=', Currencies::FUNCTIONAL)
            ->with(['paymentApplications', 'creditMemoApplications', 'customer'])
            ->get();

        foreach ($invoices as $invoice) {
            $row = $this->revalue('AR', $companyId, $asOf, $invoice->outstanding(), (float) $invoice->exchange_rate, $invoice->currency_code, (string) $invoice->number, $invoice->customer?->name);
            if ($row !== null) {
                $rows[] = $row;
                $totalUnrealized += (int) $row['unrealized'];
            }
        }

        $bills = Bill::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
            ->where('currency_code', '!=', Currencies::FUNCTIONAL)
            ->with(['applications', 'debitMemoApplications', 'vendor'])
            ->get();

        foreach ($bills as $bill) {
            $row = $this->revalue('AP', $companyId, $asOf, $bill->outstanding(), (float) $bill->exchange_rate, $bill->currency_code, (string) $bill->number, $bill->vendor?->name);
            if ($row !== null) {
                $rows[] = $row;
                $totalUnrealized += (int) $row['unrealized'];
            }
        }

        return ['rows' => $rows, 'total_unrealized' => $totalUnrealized];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function revalue(string $type, int $companyId, string $asOf, int $booked, float $issueRate, string $currency, string $number, ?string $party): ?array
    {
        if ($booked <= 0 || $issueRate <= 0.0) {
            return null;
        }

        try {
            $currentRate = $this->rates->rateFor($companyId, $currency, $asOf);
        } catch (Throwable) {
            return null; // no period-end rate to revalue against
        }

        $foreignOutstanding = $booked / $issueRate;
        $revalued = (int) round($foreignOutstanding * $currentRate);

        // For a receivable a higher PHP value is a gain; for a payable it is a loss.
        $unrealized = $type === 'AR' ? $revalued - $booked : $booked - $revalued;

        return [
            'type' => $type,
            'number' => $number,
            'party' => $party,
            'currency' => $currency,
            'foreign_outstanding' => (int) round($foreignOutstanding),
            'booked' => $booked,
            'revalued' => $revalued,
            'unrealized' => $unrealized,
        ];
    }
}

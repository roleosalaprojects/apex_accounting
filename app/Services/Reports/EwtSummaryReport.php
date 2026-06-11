<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\WithholdingTransaction;

/**
 * EWT Summary (§12.15): per vendor/ATC for 0619-E / 1601-EQ / 2307 generation.
 */
final class EwtSummaryReport
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total_base: int, total_ewt: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $transactions = WithholdingTransaction::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $asOf)
            ->with('vendor')
            ->get();

        $grouped = [];
        $totalBase = 0;
        $totalEwt = 0;

        foreach ($transactions as $tx) {
            $key = $tx->vendor_id.':'.$tx->atc;
            $grouped[$key] ??= [
                'vendor' => $tx->vendor?->name,
                'tin' => $tx->vendor?->tin,
                'atc' => $tx->atc,
                'rate_bp' => $tx->rate_bp,
                'base' => 0,
                'ewt' => 0,
            ];
            $grouped[$key]['base'] += $tx->base->minor;
            $grouped[$key]['ewt'] += $tx->ewt->minor;
            $totalBase += $tx->base->minor;
            $totalEwt += $tx->ewt->minor;
        }

        return ['rows' => array_values($grouped), 'total_base' => $totalBase, 'total_ewt' => $totalEwt];
    }
}

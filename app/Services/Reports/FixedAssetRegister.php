<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AssetStatus;
use App\Enums\JournalStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\Assets\DepreciationSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Fixed asset register / lapsing schedule (§10): every asset held at any time
 * in the period — its cost and life, the depreciation brought forward, the
 * period's charge, its disposal if any, and the accumulated depreciation and
 * net book value carried forward — with the ledger's asset and accumulated
 * depreciation balances as of the same date, so the register can be seen to
 * tie.
 */
final class FixedAssetRegister
{
    public function __construct(private readonly DepreciationSchedule $schedule) {}

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>, ledger: array{cost: int, accumulated: int}}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $assets = Asset::query()->withoutGlobalScopes()->with('category')
            ->where('company_id', $companyId)
            ->whereDate('acquisition_date', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('disposed_at')->orWhereDate('disposed_at', '>=', $from))
            ->get()
            ->sortBy(fn (Asset $asset): string => ($asset->category->name ?? '').'|'.($asset->number ?? '').'|'.$asset->id)
            ->values();

        $before = $this->depreciationByAsset($companyId, null, $from);
        $during = $this->depreciationByAsset($companyId, $from, $asOf);

        $rows = [];
        $totals = ['cost' => 0, 'accumulated_start' => 0, 'depreciation' => 0, 'accumulated_end' => 0, 'net_book_value' => 0, 'proceeds' => 0, 'gain_loss' => 0];
        foreach ($assets as $asset) {
            $cost = $asset->acquisition_cost->minor;
            $accumulatedStart = (int) ($before[$asset->id] ?? 0);
            $charge = (int) ($during[$asset->id] ?? 0);
            $disposedInPeriod = $asset->disposed_at !== null && $asset->disposed_at->toDateString() <= $asOf;
            $accumulatedEnd = $disposedInPeriod ? 0 : $accumulatedStart + $charge;
            $netBookValue = $disposedInPeriod ? 0 : $cost - $accumulatedEnd;

            $rows[] = [
                'asset_id' => $asset->id,
                'number' => $asset->number,
                'name' => $asset->name,
                'category' => $asset->category?->name,
                'acquisition_date' => $asset->acquisition_date->toDateString(),
                'in_service_date' => $asset->in_service_date?->toDateString(),
                'cost' => $cost,
                'salvage' => $asset->salvage_value->minor,
                'life_months' => $asset->useful_life_months,
                'monthly' => $this->schedule->amountForEntry($asset->depreciableBase(), $asset->useful_life_months, 1),
                'accumulated_start' => $accumulatedStart,
                'depreciation' => $charge,
                'accumulated_end' => $accumulatedEnd,
                'net_book_value' => $netBookValue,
                'status' => $disposedInPeriod ? AssetStatus::Disposed->value : $asset->status->value,
                'disposed_on' => $disposedInPeriod ? $asset->disposed_at->toDateString() : null,
                'proceeds' => $disposedInPeriod ? $asset->disposal_proceeds : null,
                'gain_loss' => $disposedInPeriod ? $asset->disposal_gain_loss : null,
            ];

            $totals['cost'] += $disposedInPeriod ? 0 : $cost;
            $totals['accumulated_start'] += $accumulatedStart;
            $totals['depreciation'] += $charge;
            $totals['accumulated_end'] += $accumulatedEnd;
            $totals['net_book_value'] += $netBookValue;
            $totals['proceeds'] += $disposedInPeriod ? (int) $asset->disposal_proceeds : 0;
            $totals['gain_loss'] += $disposedInPeriod ? (int) $asset->disposal_gain_loss : 0;
        }

        return ['rows' => $rows, 'totals' => $totals, 'ledger' => $this->ledger($companyId, $asOf)];
    }

    /**
     * Depreciation per asset for the periods starting in [$from, $to), or before $to when $from is null.
     *
     * @return array<int, int>
     */
    private function depreciationByAsset(int $companyId, ?string $from, string $to): array
    {
        $query = DB::table('depreciation_entries')
            ->join('accounting_periods', 'accounting_periods.id', '=', 'depreciation_entries.period_id')
            ->where('depreciation_entries.company_id', $companyId);
        if ($from === null) {
            $query->whereDate('accounting_periods.starts_on', '<', $to);
        } else {
            $query->whereDate('accounting_periods.starts_on', '>=', $from)->whereDate('accounting_periods.starts_on', '<=', $to);
        }

        return $query->groupBy('depreciation_entries.asset_id')
            ->selectRaw('depreciation_entries.asset_id, SUM(depreciation_entries.amount) as amount')
            ->pluck('amount', 'asset_id')->map(fn (mixed $v): int => (int) $v)->all();
    }

    /**
     * The ledger's fixed-asset cost and accumulated depreciation as of the date, across every category.
     *
     * @return array{cost: int, accumulated: int}
     */
    private function ledger(int $companyId, string $asOf): array
    {
        $categories = AssetCategory::query()->withoutGlobalScopes()->where('company_id', $companyId)->get();
        $balance = function (array $accountIds) use ($companyId, $asOf): int {
            return (int) DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.company_id', $companyId)
                ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
                ->whereDate('journal_entries.entry_date', '<=', $asOf)
                ->whereIn('journal_lines.account_id', $accountIds)
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
                ->value('balance');
        };

        return [
            'cost' => $balance($categories->pluck('fixed_asset_account_id')->unique()->all()),
            'accumulated' => -$balance($categories->pluck('accum_depreciation_account_id')->unique()->all()),
        ];
    }
}

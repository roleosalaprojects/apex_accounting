<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\AssetStatus;
use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Assets\DepreciationSchedule;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

/**
 * Runs depreciation for one period (§10.2). One summary JE per category
 * (Dr Depreciation Expense / Cr Accumulated Depreciation) plus per-asset
 * depreciation_entries. Idempotent per period — assets already depreciated in
 * the period are skipped (unique asset_id+period_id).
 */
final class RunMonthlyDepreciation
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly DepreciationSchedule $schedule,
    ) {}

    /**
     * @return array<int, JournalEntry>
     */
    public function handle(Company $company, AccountingPeriod $period, ?User $actor = null): array
    {
        return DB::transaction(function () use ($company, $period, $actor): array {
            $categories = AssetCategory::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->get();

            $entries = [];

            foreach ($categories as $category) {
                $assets = Asset::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('asset_category_id', $category->id)
                    ->where('status', AssetStatus::InService->value)
                    ->get();

                $categoryTotal = 0;
                $pending = [];

                foreach ($assets as $asset) {
                    $already = $asset->depreciationEntries()->where('period_id', $period->id)->exists();
                    if ($already) {
                        continue;
                    }

                    $entryNo = $asset->depreciationEntries()->count() + 1;
                    $amount = $this->schedule->amountForEntry($asset->depreciableBase(), $asset->useful_life_months, $entryNo);

                    if ($amount <= 0) {
                        continue;
                    }

                    $categoryTotal += $amount;
                    $pending[] = ['asset' => $asset, 'amount' => $amount, 'entry_no' => $entryNo];
                }

                if ($categoryTotal === 0) {
                    continue;
                }

                $journal = $this->post->handle(new JournalEntryData(
                    company_id: $company->id,
                    entry_date: $period->ends_on->toDateString(),
                    memo: 'Depreciation — '.$category->name.' '.$period->fiscal_year.'-'.$period->period_no,
                    lines: new DataCollection(JournalLineData::class, [
                        new JournalLineData(account_id: $category->depreciation_expense_account_id, debit: $categoryTotal, memo: 'Depreciation expense'),
                        new JournalLineData(account_id: $category->accum_depreciation_account_id, credit: $categoryTotal, memo: 'Accumulated depreciation'),
                    ]),
                    created_by: $actor?->id,
                    approved_by: $actor?->id,
                ), $actor);

                foreach ($pending as $item) {
                    /** @var Asset $asset */
                    $asset = $item['asset'];
                    $asset->depreciationEntries()->create([
                        'company_id' => $company->id,
                        'period_id' => $period->id,
                        'amount' => $item['amount'],
                        'journal_entry_id' => $journal->id,
                    ]);

                    if ($item['entry_no'] >= $asset->useful_life_months) {
                        $asset->forceFill(['status' => AssetStatus::FullyDepreciated])->save();
                    }
                }

                $entries[] = $journal;
            }

            return $entries;
        });
    }
}

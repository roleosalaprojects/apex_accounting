<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\AssetStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use App\Services\Tax\VatMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Disposes an asset (§10.2):
 *   Dr cash/bank                       proceeds
 *   Dr Accumulated Depreciation        accumulated to date
 *   Dr/Cr Gain/Loss on Disposal        balancing figure
 *      Cr Fixed Assets                 acquisition cost
 *      [Cr Output VAT                  when the disposal is VATable]
 */
final class DisposeAsset
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
    ) {}

    public function handle(
        Asset $asset,
        string $date,
        int $proceeds,
        int $depositToAccountId,
        bool $vatable = false,
        ?User $actor = null,
    ): Asset {
        if (in_array($asset->status, [AssetStatus::Disposed, AssetStatus::Draft], true)) {
            throw new RuntimeException('Only an in-service or fully-depreciated asset can be disposed.');
        }

        return DB::transaction(function () use ($asset, $date, $proceeds, $depositToAccountId, $vatable, $actor): Asset {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($asset->company_id);
            $category = $asset->category()->withoutGlobalScopes()->firstOrFail();

            $vatAmount = $vatable ? $this->vat->fromInclusive($proceeds, 1200)->vat : 0;
            $netProceeds = $proceeds - $vatAmount;
            $accumulated = $asset->accumulatedDepreciation();
            $cost = $asset->acquisition_cost->minor;
            $netBookValue = $cost - $accumulated;
            $gainLoss = $netProceeds - $netBookValue; // + gain, − loss

            $lines = [
                new JournalLineData(account_id: $depositToAccountId, debit: $proceeds, memo: 'Disposal proceeds'),
                new JournalLineData(account_id: $category->accum_depreciation_account_id, debit: $accumulated, memo: 'Clear accumulated depreciation'),
                new JournalLineData(account_id: $category->fixed_asset_account_id, credit: $cost, memo: 'Remove asset cost'),
            ];

            if ($vatAmount > 0) {
                $lines[] = new JournalLineData(account_id: $this->account($company, '2200')->id, credit: $vatAmount, memo: 'Output VAT on disposal');
            }

            if ($gainLoss > 0) {
                $lines[] = new JournalLineData(account_id: $this->account($company, '4900')->id, credit: $gainLoss, memo: 'Gain on disposal');
            } elseif ($gainLoss < 0) {
                $lines[] = new JournalLineData(account_id: $this->account($company, '4900')->id, debit: -$gainLoss, memo: 'Loss on disposal');
            }

            $this->post->handle(new JournalEntryData(
                company_id: $company->id,
                entry_date: $date,
                memo: 'Disposal of '.($asset->number ?? $asset->name),
                lines: new DataCollection(JournalLineData::class, $lines),
                source_type: $asset->getMorphClass(),
                source_id: $asset->id,
                created_by: $actor?->id,
                approved_by: $actor?->id,
            ), $actor);

            $asset->forceFill(['status' => AssetStatus::Disposed, 'disposed_at' => $date])->save();

            return $asset;
        });
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}

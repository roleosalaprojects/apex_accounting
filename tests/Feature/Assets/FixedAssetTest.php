<?php

declare(strict_types=1);

use App\Actions\Assets\DisposeAsset;
use App\Actions\Assets\PlaceAssetInService;
use App\Actions\Assets\RunMonthlyDepreciation;
use App\Enums\AssetStatus;
use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\PeriodBalance;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->category = AssetCategory::factory()->create([
        'company_id' => $this->company->id,
        'fixed_asset_account_id' => account($this->company, '1500')->id,
        'accum_depreciation_account_id' => account($this->company, '1510')->id,
        'depreciation_expense_account_id' => account($this->company, '6800')->id,
    ]);
});

function periodNo(int $n): AccountingPeriod
{
    return test()->company->periods()->where('period_no', $n)->first();
}

function assetBal(string $code): int
{
    $p6 = test()->company->periods()->where('period_no', 6)->value('id');
    $row = PeriodBalance::query()->where('account_id', account(test()->company, $code)->id)->where('period_id', $p6)->first();

    return $row?->closing->minor ?? 0;
}

it('posts the first monthly depreciation (₱3,333.33) and is idempotent per period', function () {
    $asset = Asset::factory()->create([
        'company_id' => $this->company->id, 'asset_category_id' => $this->category->id,
        'acquisition_cost' => 120_000_00, 'useful_life_months' => 36,
    ]);
    app(PlaceAssetInService::class)->handle($asset, '2026-06-01');

    app(RunMonthlyDepreciation::class)->handle($this->company->fresh(), periodNo(6));
    app(RunMonthlyDepreciation::class)->handle($this->company->fresh(), periodNo(6)); // re-run: no double

    expect(assetBal('6800'))->toBe(3_333_33)
        ->and(assetBal('1510'))->toBe(-3_333_33) // contra-asset credit
        ->and($asset->fresh()->depreciationEntries()->count())->toBe(1);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('depreciates fully then stops', function () {
    $asset = Asset::factory()->create([
        'company_id' => $this->company->id, 'asset_category_id' => $this->category->id,
        'acquisition_cost' => 1_000_00, 'salvage_value' => 100_00, 'useful_life_months' => 3,
    ]);
    app(PlaceAssetInService::class)->handle($asset, '2026-06-01');

    foreach ([6, 7, 8, 9] as $n) {
        app(RunMonthlyDepreciation::class)->handle($this->company->fresh(), periodNo($n));
    }

    expect($asset->fresh()->accumulatedDepreciation())->toBe(900_00) // depreciable base
        ->and($asset->fresh()->status)->toBe(AssetStatus::FullyDepreciated)
        ->and($asset->fresh()->depreciationEntries()->count())->toBe(3); // 4th run added nothing
});

it('disposes an asset at a gain — entry balances', function () {
    $asset = Asset::factory()->create([
        'company_id' => $this->company->id, 'asset_category_id' => $this->category->id,
        'acquisition_cost' => 100_000_00, 'salvage_value' => 0, 'useful_life_months' => 10,
    ]);
    app(PlaceAssetInService::class)->handle($asset, '2026-06-01');
    app(RunMonthlyDepreciation::class)->handle($this->company->fresh(), periodNo(6)); // accum 10,000, NBV 90,000

    app(DisposeAsset::class)->handle($asset->fresh(), '2026-06-30', 95_000_00, account($this->company, '1120')->id);

    expect($asset->fresh()->status)->toBe(AssetStatus::Disposed)
        ->and(assetBal('4900'))->toBe(-5_000_00); // ₱5,000 gain (credit)

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('disposes an asset at a loss — entry balances', function () {
    $asset = Asset::factory()->create([
        'company_id' => $this->company->id, 'asset_category_id' => $this->category->id,
        'acquisition_cost' => 100_000_00, 'salvage_value' => 0, 'useful_life_months' => 10,
    ]);
    app(PlaceAssetInService::class)->handle($asset, '2026-06-01');
    app(RunMonthlyDepreciation::class)->handle($this->company->fresh(), periodNo(6)); // NBV 90,000

    app(DisposeAsset::class)->handle($asset->fresh(), '2026-06-30', 80_000_00, account($this->company, '1120')->id);

    expect(assetBal('4900'))->toBe(10_000_00); // ₱10,000 loss (debit)

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

<?php

declare(strict_types=1);

use App\Actions\Assets\DisposeAsset;
use App\Actions\Assets\PlaceAssetInService;
use App\Actions\Assets\RunMonthlyDepreciation;
use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Enums\AssetStatus;
use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\FixedAssetRegisterPage;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Reports\FixedAssetRegister;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

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

/*
 * The fixed asset register / lapsing schedule: every asset held during the
 * period with its cost, the depreciation brought forward, the period's
 * charge, disposals and the net book value carried forward — tying to the
 * ledger's asset and accumulated-depreciation accounts.
 */
function makeRegisterFixtures($test): array
{
    $truck = Asset::factory()->create([
        'company_id' => $test->company->id, 'asset_category_id' => $test->category->id, 'number' => 'FA-0001', 'name' => 'Delivery truck',
        'acquisition_date' => '2026-01-10', 'acquisition_cost' => 120_000_00, 'salvage_value' => 0, 'useful_life_months' => 36,
    ]);
    $laptop = Asset::factory()->create([
        'company_id' => $test->company->id, 'asset_category_id' => $test->category->id, 'number' => 'FA-0002', 'name' => 'Laptop',
        'acquisition_date' => '2026-03-01', 'acquisition_cost' => 60_000_00, 'salvage_value' => 0, 'useful_life_months' => 24,
    ]);
    // Bought through bills, so the cost sits in 1500 like a real purchase would.
    $vendor = Vendor::factory()->create(['company_id' => $test->company->id]);
    $exempt = TaxCode::query()->where('company_id', $test->company->id)->where('code', 'EXEMPT')->value('id');
    foreach ([$truck, $laptop] as $asset) {
        app(PostBill::class)->handle(BillData::from([
            'company_id' => $test->company->id, 'vendor_id' => $vendor->id, 'bill_date' => $asset->acquisition_date->toDateString(),
            'lines' => [['description' => $asset->name, 'qty' => '1', 'unit_price' => $asset->acquisition_cost->minor, 'tax_code_id' => $exempt,
                'expense_or_asset_account_id' => account($test->company, '1500')->id]],
        ]));
    }
    app(PlaceAssetInService::class)->handle($truck, '2026-01-10');
    app(PlaceAssetInService::class)->handle($laptop, '2026-03-01');
    foreach ([1, 2, 3, 4, 5, 6] as $n) {
        app(RunMonthlyDepreciation::class)->handle($test->company->fresh(), periodNo($n));
    }
    // Truck: 6 × 3,333.33 = 19,999.98 accumulated; laptop (in service March): 4 × 2,500 = 10,000.

    return [$truck, $laptop];
}

it('lists every asset on the register with depreciation brought forward, charged and carried forward', function () {
    [$truck, $laptop] = makeRegisterFixtures($this);
    app(DisposeAsset::class)->handle($laptop->fresh(), '2026-06-30', 45_000_00, account($this->company, '1120')->id); // NBV 50,000 → loss 5,000

    $register = app(FixedAssetRegister::class)->build($this->company->id, '2026-04-01', '2026-06-30');

    expect($register['rows'])->toHaveCount(2);
    expect($register['rows'][0])->toMatchArray([
        'number' => 'FA-0001', 'name' => 'Delivery truck', 'cost' => 120_000_00, 'salvage' => 0, 'life_months' => 36, 'monthly' => 3_333_33,
        'accumulated_start' => 9_999_99, 'depreciation' => 9_999_99, 'accumulated_end' => 19_999_98, 'net_book_value' => 100_000_02, 'status' => 'in_service',
        'disposed_on' => null, 'proceeds' => null, 'gain_loss' => null,
    ]);
    expect($register['rows'][1])->toMatchArray([
        'number' => 'FA-0002', 'cost' => 60_000_00, 'accumulated_start' => 2_500_00, 'depreciation' => 7_500_00,
        'accumulated_end' => 0, 'net_book_value' => 0, 'status' => 'disposed',
        'disposed_on' => '2026-06-30', 'proceeds' => 45_000_00, 'gain_loss' => -5_000_00,
    ]);
    expect($register['totals'])->toMatchArray(['cost' => 120_000_00, 'depreciation' => 17_499_99, 'accumulated_end' => 19_999_98, 'net_book_value' => 100_000_02])
        ->and($register['ledger'])->toBe(['cost' => 120_000_00, 'accumulated' => 19_999_98]);

    // An asset disposed before the period is history; one acquired after it is not there yet.
    $q1 = app(FixedAssetRegister::class)->build($this->company->id, '2026-01-01', '2026-02-28');
    expect(array_column($q1['rows'], 'number'))->toBe(['FA-0001'])
        ->and($q1['rows'][0])->toMatchArray(['accumulated_start' => 0, 'depreciation' => 6_666_66, 'accumulated_end' => 6_666_66]);
    $later = app(FixedAssetRegister::class)->build($this->company->id, '2026-07-01', '2026-07-31');
    expect(array_column($later['rows'], 'number'))->toBe(['FA-0001']);
});

it('shows the register page tying to the ledger, and an asset page with its schedule', function () {
    [$truck] = makeRegisterFixtures($this);
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    Livewire::test(FixedAssetRegisterPage::class)
        ->set('from', '2026-01-01')->set('asOf', '2026-06-30')
        ->assertSee('Delivery truck')->assertSee('19,999.98')->assertSee('tie to the ledger as of');

    Livewire::test(ViewAsset::class, ['record' => $truck->getRouteKey()])
        ->assertSee('FA-0001')
        ->assertSee('100,000.02')       // net book value
        ->assertSee('Dec 2028');        // the 36th and last monthly charge
});

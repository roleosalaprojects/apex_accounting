<?php

declare(strict_types=1);

use App\Enums\AssetStatus;
use App\Enums\CompanyRole;
use App\Enums\PeriodStatus;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Filament\Resources\AssetCategories\Pages\CreateAssetCategory;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\RecurringTemplates\Pages\CreateRecurringTemplate;
use App\Filament\Resources\RecurringTemplates\Pages\ListRecurringTemplates;
use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\JournalEntry;
use App\Models\RecurringTemplate;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('records a deposit from the banking page', function () {
    Livewire::test(ListBankAccounts::class)
        ->callAction('deposit', [
            'bank_account_id' => account($this->company, '1120')->id,
            'source_account_id' => account($this->company, '1110')->id,
            'date' => '2026-06-15',
            'amount' => '5000',
            'memo' => 'Daily cash deposit',
        ])
        ->assertHasNoActionErrors();

    $je = JournalEntry::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($je)->not->toBeNull()
        ->and($je->status->value)->toBe('posted')
        ->and($je->total_debits->minor)->toBe(5_000_00);
});

it('closes and reopens a period from the periods page', function () {
    $period = AccountingPeriod::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->whereDate('starts_on', '2026-06-01')->firstOrFail();

    Livewire::test(ListAccountingPeriods::class)
        ->callAction(TestAction::make('close')->table($period))
        ->assertHasNoActionErrors();
    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);

    Livewire::test(ListAccountingPeriods::class)
        ->callAction(TestAction::make('reopen')->table($period))
        ->assertHasNoActionErrors();
    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
});

it('creates an asset category and an asset, then places it in service and runs depreciation', function () {
    Livewire::test(CreateAssetCategory::class)
        ->fillForm([
            'name' => 'Office Equipment',
            'fixed_asset_account_id' => account($this->company, '1500')->id,
            'accum_depreciation_account_id' => account($this->company, '1510')->id,
            'depreciation_expense_account_id' => account($this->company, '6800')->id,
            'default_useful_life_months' => 60,
            'method' => 'straight_line',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = AssetCategory::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();

    Livewire::test(CreateAsset::class)
        ->fillForm([
            'asset_category_id' => $category->id,
            'name' => 'Laptop',
            'number' => 'FA-001',
            'acquisition_date' => '2026-05-01',
            'acquisition_cost' => '60000',
            'salvage_value' => '0',
            'useful_life_months' => 60,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $asset = Asset::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();
    expect($asset->status)->toBe(AssetStatus::Draft)
        ->and($asset->acquisition_cost->minor)->toBe(60_000_00);

    Livewire::test(ListAssets::class)
        ->callAction(TestAction::make('placeInService')->table($asset), ['in_service_date' => '2026-05-01'])
        ->assertHasNoActionErrors();
    expect($asset->fresh()->status)->toBe(AssetStatus::InService);

    $june = AccountingPeriod::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->whereDate('starts_on', '2026-06-01')->firstOrFail();

    Livewire::test(ListAssets::class)
        ->callAction('runDepreciation', ['period_id' => $june->id])
        ->assertHasNoActionErrors();

    // 60,000 / 60 months = P1,000 monthly depreciation.
    expect((int) $asset->depreciationEntries()->where('period_id', $june->id)->sum('amount'))->toBe(1_000_00);
});

it('creates a recurring JE template and runs it from the UI', function () {
    $payload = json_encode([
        'memo' => 'Monthly rent paid in cash',
        'lines' => [
            ['account_id' => account($this->company, '6100')->id, 'debit' => 10_000_00],
            ['account_id' => account($this->company, '1110')->id, 'credit' => 10_000_00],
        ],
    ]);

    Livewire::test(CreateRecurringTemplate::class)
        ->fillForm([
            'name' => 'Monthly rent',
            'kind' => 'journal_entry',
            'schedule' => 'monthly',
            'day_of_month' => 1,
            'starts_on' => '2026-06-01',
            'auto_post' => true,
            'is_active' => true,
            'payload' => $payload,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = RecurringTemplate::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();
    expect($template->next_run_on->toDateString())->toBe('2026-06-01');

    Livewire::test(ListRecurringTemplates::class)
        ->callAction('runDue')
        ->assertHasNoActionErrors();

    expect($template->runs()->where('status', 'posted')->count())->toBe(1)
        ->and(JournalEntry::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->where('status', 'posted')->count())->toBe(1);
});

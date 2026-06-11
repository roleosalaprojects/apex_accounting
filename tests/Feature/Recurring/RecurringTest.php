<?php

declare(strict_types=1);

use App\Actions\Assets\PlaceAssetInService;
use App\Actions\Recurring\RunDueTemplates;
use App\Enums\JournalStatus;
use App\Enums\RecurringKind;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\JournalEntry;
use App\Models\RecurringTemplate;

beforeEach(function () {
    $this->company = makeCompany();
});

function rentPayload(): array
{
    return ['lines' => [
        ['account_id' => account(test()->company, '6100')->id, 'debit' => 50_000_00],
        ['account_id' => account(test()->company, '1120')->id, 'credit' => 50_000_00],
    ]];
}

it('runs only due templates and advances next_run_on', function () {
    $due = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01',
    ]);
    $notDue = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-07-01',
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->status)->toBe('posted')
        ->and($due->fresh()->next_run_on->toDateString())->toBe('2026-07-01')   // advanced
        ->and($notDue->fresh()->next_run_on->toDateString())->toBe('2026-07-01'); // untouched
});

it('creates a draft when auto_post is false, and posts when true', function () {
    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => false, 'next_run_on' => '2026-06-01',
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');

    expect($runs[0]->status)->toBe('created')
        ->and(JournalEntry::query()->where('status', JournalStatus::Draft->value)->count())->toBe(1)
        ->and(JournalEntry::query()->where('status', JournalStatus::Posted->value)->count())->toBe(0);
});

it('isolates failures — one bad template does not halt the batch', function () {
    // Bad: unbalanced payload.
    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => ['lines' => [
            ['account_id' => account($this->company, '6100')->id, 'debit' => 50_000_00],
            ['account_id' => account($this->company, '1120')->id, 'credit' => 40_000_00],
        ]],
        'auto_post' => true, 'next_run_on' => '2026-06-01', 'name' => 'broken',
    ]);
    $good = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01', 'name' => 'good',
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');

    $statuses = collect($runs)->pluck('status')->sort()->values()->all();
    expect($statuses)->toBe(['failed', 'posted'])
        ->and($good->fresh()->next_run_on->toDateString())->toBe('2026-07-01'); // good advanced, bad did not
});

it('triggers the Phase-7 depreciation run from a depreciation_run template', function () {
    $category = AssetCategory::factory()->create([
        'company_id' => $this->company->id,
        'fixed_asset_account_id' => account($this->company, '1500')->id,
        'accum_depreciation_account_id' => account($this->company, '1510')->id,
        'depreciation_expense_account_id' => account($this->company, '6800')->id,
    ]);
    $asset = Asset::factory()->create([
        'company_id' => $this->company->id, 'asset_category_id' => $category->id,
        'acquisition_cost' => 120_000_00, 'useful_life_months' => 36,
    ]);
    app(PlaceAssetInService::class)->handle($asset, '2026-06-01');

    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::DepreciationRun,
        'payload' => null, 'auto_post' => true, 'next_run_on' => '2026-06-30',
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-30');

    expect($runs[0]->status)->toBe('posted')
        ->and($asset->fresh()->depreciationEntries()->count())->toBe(1);
});

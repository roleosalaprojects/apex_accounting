<?php

declare(strict_types=1);

use App\Actions\Assets\PlaceAssetInService;
use App\Actions\Recurring\RunDueTemplates;
use App\Enums\CompanyRole;
use App\Enums\JournalStatus;
use App\Enums\RecurringKind;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\RecurringTemplate;
use App\Models\TaxCode;

beforeEach(function () {
    $this->company = makeCompany();
    // Runs post under the authority of whoever last saved the template.
    $this->accountant = makeUserWithRole($this->company, CompanyRole::Accountant);
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
        'updated_by' => $this->accountant->id,
    ]);
    $notDue = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-07-01',
        'updated_by' => $this->accountant->id,
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
        'updated_by' => $this->accountant->id,
    ]);
    $good = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01', 'name' => 'good',
        'updated_by' => $this->accountant->id,
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
        'updated_by' => $this->accountant->id,
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-30');

    expect($runs[0]->status)->toBe('posted')
        ->and($asset->fresh()->depreciationEntries()->count())->toBe(1);
});

it('posts under the authority of whoever last saved the template', function () {
    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01',
        'updated_by' => $this->accountant->id,
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');
    $entry = JournalEntry::query()->findOrFail($runs[0]->created_document_id);

    expect($runs[0]->status)->toBe('posted')
        ->and((int) $entry->posted_by)->toBe($this->accountant->id)
        ->and((int) $entry->created_by)->toBe($this->accountant->id);
});

it('will not post a template last saved by someone without posting rights, whoever runs it', function () {
    // A bookkeeper may manage templates but not post; an edit of theirs must
    // not let the scheduler — or an accountant pressing Run — post it for them.
    $bookkeeper = makeUserWithRole($this->company, CompanyRole::Bookkeeper);
    $template = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01',
        'updated_by' => $bookkeeper->id,
    ]);

    foreach ([null, $this->accountant] as $triggeredBy) {
        $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15', $triggeredBy);

        expect($runs[0]->status)->toBe('failed')
            ->and($runs[0]->error)->toContain('lacks posting rights');
    }

    expect(JournalEntry::query()->where('status', JournalStatus::Posted->value)->count())->toBe(0)
        ->and($template->fresh()->next_run_on->toDateString())->toBe('2026-06-01');
});

it('will not post a template with no editor on record, but still drafts one', function () {
    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => true, 'next_run_on' => '2026-06-01', 'name' => 'orphan post',
    ]);
    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => rentPayload(), 'auto_post' => false, 'next_run_on' => '2026-06-01', 'name' => 'orphan draft',
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');

    expect(collect($runs)->pluck('status')->sort()->values()->all())->toBe(['created', 'failed'])
        ->and(JournalEntry::query()->where('status', JournalStatus::Posted->value)->count())->toBe(0);
});

it('ignores signatories and ledger links smuggled into the payload', function () {
    $owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $unrelated = postEntry($this->company, '2026-06-01', rentPayload()['lines']);

    RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'payload' => [
            ...rentPayload(),
            'created_by' => $owner->id, 'approved_by' => $owner->id,
            'source_type' => 'pos.zreading', 'source_id' => 1,
            'reversal_of_id' => $unrelated->id, 'reversal_reason' => 'forged',
        ],
        'auto_post' => true, 'next_run_on' => '2026-06-01', 'updated_by' => $this->accountant->id,
    ]);

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');
    $entry = JournalEntry::query()->findOrFail($runs[0]->created_document_id);

    expect($runs[0]->status)->toBe('posted')
        ->and((int) $entry->created_by)->toBe($this->accountant->id)
        ->and((int) $entry->approved_by)->toBe($this->accountant->id)
        ->and($entry->source_type)->toBeNull()
        ->and($entry->reversal_of_id)->toBeNull();
});

it('applies the same authority rule to invoice templates', function () {
    $bookkeeper = makeUserWithRole($this->company, CompanyRole::Bookkeeper);
    $payload = [
        'customer_id' => Customer::factory()->create(['company_id' => $this->company->id])->id,
        'lines' => [[
            'description' => 'Monthly retainer', 'qty' => '1', 'unit_price' => 1_120_00,
            'tax_code_id' => TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id'),
            'income_account_id' => account($this->company, '4200')->id,
        ]],
    ];

    foreach ([$bookkeeper, $this->accountant] as $editor) {
        RecurringTemplate::factory()->create([
            'company_id' => $this->company->id, 'kind' => RecurringKind::Invoice,
            'payload' => $payload, 'next_run_on' => '2026-06-01', 'updated_by' => $editor->id,
        ]);
    }

    $runs = app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15');

    expect(collect($runs)->pluck('status')->sort()->values()->all())->toBe(['failed', 'posted'])
        ->and(Invoice::query()->count())->toBe(1);
});

it('only lets members with recurring.run trigger a run', function () {
    $bookkeeper = makeUserWithRole($this->company, CompanyRole::Bookkeeper);

    expect(fn () => app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15', $bookkeeper))
        ->toThrow(RuntimeException::class, 'permission to run recurring templates')
        ->and(app(RunDueTemplates::class)->handle($this->company->fresh(), '2026-06-15', $this->accountant))->toBe([]);
});

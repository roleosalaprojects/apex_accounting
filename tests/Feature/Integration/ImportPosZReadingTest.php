<?php

declare(strict_types=1);

use App\Actions\Integration\ImportPosZReading;
use App\Enums\CompanyRole;
use App\Enums\JournalStatus;
use App\Enums\PosZReadingStatus;
use App\Models\JournalEntry;
use App\Models\PosZReading;

beforeEach(function () {
    $this->company = makeCompany();
    $this->user = makeUserWithRole($this->company, CompanyRole::Accountant);
});

function pendingReading(int $companyId): PosZReading
{
    return PosZReading::factory()->create([
        'company_id' => $companyId,
        'reference' => 'Z-0007',
    ]);
}

it('imports a pending Z-reading into a balanced DRAFT journal entry', function () {
    $reading = pendingReading($this->company->id);

    $draft = app(ImportPosZReading::class)->handle($reading, $this->user);

    // A draft — not posted — so it has no GL effect until an admin reviews it.
    expect($draft->status)->toBe(JournalStatus::Draft)
        ->and($draft->total_debits->minor)->toBe(162_000_00)
        ->and($draft->total_credits->minor)->toBe(162_000_00)
        ->and($draft->source_type)->toBe('pos.zreading')
        ->and($draft->source_id)->toBe($reading->id)
        ->and($draft->external_reference_no)->toBe('Z-0007');

    $draft->load('lines');
    expect($draft->lines->firstWhere('account_id', account($this->company, '1110')->id)->debit->minor)->toBe(100_000_00)
        ->and($draft->lines->firstWhere('account_id', account($this->company, '2200')->id)->credit->minor)->toBe(12_000_00);
});

it('marks the reading imported and links it to the draft', function () {
    $reading = pendingReading($this->company->id);

    $draft = app(ImportPosZReading::class)->handle($reading, $this->user);

    $reading->refresh();
    expect($reading->status)->toBe(PosZReadingStatus::Imported)
        ->and($reading->journal_entry_id)->toBe($draft->id)
        ->and($reading->imported_by)->toBe($this->user->id)
        ->and($reading->imported_at)->not->toBeNull();
});

it('refuses to import the same Z-reading twice', function () {
    $reading = pendingReading($this->company->id);

    app(ImportPosZReading::class)->handle($reading, $this->user);

    expect(fn () => app(ImportPosZReading::class)->handle($reading->refresh(), $this->user))
        ->toThrow(RuntimeException::class);

    // Only one draft was ever created.
    expect(JournalEntry::query()->count())->toBe(1);
});

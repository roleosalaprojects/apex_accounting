<?php

declare(strict_types=1);

use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\PostJournalEntry;
use App\Actions\Ledger\SetupNewCompany;
use App\Data\Ledger\JournalEntryData;
use App\Enums\CompanyRole;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Create a fully set-up company: default chart of accounts, document sequences,
 * an opened fiscal year, and the active company context.
 */
function makeCompany(array $overrides = [], int $year = 2026): Company
{
    $company = Company::factory()->create($overrides);

    app(SetupNewCompany::class)->handle($company);
    app(OpenFiscalYear::class)->handle($company, $year);

    app(CompanyContext::class)->set($company->id);

    return $company;
}

function account(Company $company, string $code): Account
{
    return Account::query()
        ->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('code', $code)
        ->firstOrFail();
}

function makeUserWithRole(Company $company, CompanyRole $role): User
{
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => $role->value]);

    return $user;
}

/**
 * Build a JournalEntryData and post it. Lines are plain arrays hydrated into
 * JournalLineData (e.g. ['account_id' => 1, 'debit' => 100_000]).
 *
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array<string, mixed>  $attrs
 */
function postEntry(Company $company, string $date, array $lines, ?User $actor = null, array $attrs = []): JournalEntry
{
    $data = JournalEntryData::from(array_merge([
        'company_id' => $company->id,
        'entry_date' => $date,
        'lines' => $lines,
    ], $attrs));

    return app(PostJournalEntry::class)->handle($data, $actor);
}

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

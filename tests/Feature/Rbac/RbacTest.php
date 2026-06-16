<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\UnapprovedDocumentException;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * @param  array<int, array{0: string, 1: string, 2: int}>  $legs  [code, 'debit'|'credit', amount]
 * @return array<int, array<string, mixed>>
 */
function balancedLines($company, array $legs): array
{
    return array_map(fn (array $leg): array => [
        'account_id' => account($company, $leg[0])->id,
        $leg[1] => $leg[2],
    ], $legs);
}

it('grants fine-grained permissions that match each standard role', function () {
    $company = makeCompany();

    $owner = makeUserWithRole($company, CompanyRole::Owner);
    $accountant = makeUserWithRole($company, CompanyRole::Accountant);
    $approver = makeUserWithRole($company, CompanyRole::Approver);
    $bookkeeper = makeUserWithRole($company, CompanyRole::Bookkeeper);
    $viewer = makeUserWithRole($company, CompanyRole::Viewer);

    // Owner-only cross-cutting permissions.
    expect($owner->hasCompanyPermission($company->id, RbacRegistry::USERS_MANAGE))->toBeTrue()
        ->and($accountant->hasCompanyPermission($company->id, RbacRegistry::USERS_MANAGE))->toBeFalse()
        ->and($owner->hasCompanyPermission($company->id, RbacRegistry::COMPANY_EXPORT))->toBeTrue()
        ->and($accountant->hasCompanyPermission($company->id, RbacRegistry::AUDIT_VIEW))->toBeFalse();

    // Posting rights: owner/accountant/approver yes, bookkeeper/viewer no.
    expect($accountant->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeTrue()
        ->and($approver->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeTrue()
        ->and($bookkeeper->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeFalse()
        ->and($viewer->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeFalse();

    // Drafts: bookkeeper yes, viewer no. Reports: everyone with a role.
    expect($bookkeeper->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_CREATE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_CREATE))->toBeFalse()
        ->and($viewer->hasCompanyPermission($company->id, RbacRegistry::REPORTS_VIEW))->toBeTrue();
});

it('lets an accountant post but forbids a bookkeeper (enforced in the Action)', function () {
    $company = makeCompany();
    $lines = balancedLines($company, [['1110', 'debit', 100_00], ['4100', 'credit', 100_00]]);

    $accountant = makeUserWithRole($company, CompanyRole::Accountant);
    $entry = postEntry($company, '2026-06-15', $lines, $accountant);
    expect($entry->status)->toBe(JournalStatus::Posted);

    $bookkeeper = makeUserWithRole($company, CompanyRole::Bookkeeper);
    expect(fn () => postEntry($company, '2026-06-15', $lines, $bookkeeper))
        ->toThrow(UnapprovedDocumentException::class);
});

it('scopes permissions to the company (spatie team isolation)', function () {
    $companyA = makeCompany(['name' => 'Alpha Corp.']);
    $companyB = makeCompany(['name' => 'Bravo Corp.']);

    $user = makeUserWithRole($companyA, CompanyRole::Owner);
    $companyB->users()->attach($user->id, ['role' => CompanyRole::Viewer->value]);
    $user->syncCompanyRole($companyB->id, CompanyRole::Viewer);

    expect($user->hasCompanyPermission($companyA->id, RbacRegistry::JOURNAL_POST))->toBeTrue()
        ->and($user->hasCompanyPermission($companyB->id, RbacRegistry::JOURNAL_POST))->toBeFalse()
        ->and($user->hasCompanyPermission($companyB->id, RbacRegistry::REPORTS_VIEW))->toBeTrue();
});

it('denies every permission to a non-member', function () {
    $company = makeCompany();
    $outsider = User::factory()->create();

    expect($outsider->hasCompanyPermission($company->id, RbacRegistry::REPORTS_VIEW))->toBeFalse()
        ->and($outsider->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeFalse();
});

it('keeps the membership pivot and the spatie assignment in step when a role changes', function () {
    $company = makeCompany();
    $user = makeUserWithRole($company, CompanyRole::Accountant);

    expect($user->roleIn($company->id))->toBe(CompanyRole::Accountant)
        ->and($user->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeTrue();

    // Demote — the same path the Team UI uses.
    $company->users()->updateExistingPivot($user->id, ['role' => CompanyRole::Viewer->value]);
    $user->syncCompanyRole($company->id, CompanyRole::Viewer);

    expect($user->hasCompanyPermission($company->id, RbacRegistry::JOURNAL_POST))->toBeFalse()
        ->and($user->hasCompanyPermission($company->id, RbacRegistry::REPORTS_VIEW))->toBeTrue();
});

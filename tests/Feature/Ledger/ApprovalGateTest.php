<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Exceptions\Ledger\UnapprovedDocumentException;

it('blocks posting an unapproved entry when the company requires approval', function () {
    $company = makeCompany(['require_approval' => true]);

    postEntry($company, '2026-06-15', [
        ['account_id' => account($company, '1120')->id, 'debit' => 100],
        ['account_id' => account($company, '3100')->id, 'credit' => 100],
    ]);
})->throws(UnapprovedDocumentException::class);

it('allows posting when the entry is flagged approved', function () {
    $company = makeCompany(['require_approval' => true]);
    $approver = makeUserWithRole($company, CompanyRole::Accountant);

    $entry = postEntry($company, '2026-06-15', [
        ['account_id' => account($company, '1120')->id, 'debit' => 100],
        ['account_id' => account($company, '3100')->id, 'credit' => 100],
    ], $approver, ['approved_by' => $approver->id]);

    expect($entry->status->value)->toBe('posted');
});

it('forbids a bookkeeper from posting', function () {
    $company = makeCompany();
    $bookkeeper = makeUserWithRole($company, CompanyRole::Bookkeeper);

    postEntry($company, '2026-06-15', [
        ['account_id' => account($company, '1120')->id, 'debit' => 100],
        ['account_id' => account($company, '3100')->id, 'credit' => 100],
    ], $bookkeeper);
})->throws(UnapprovedDocumentException::class);

it('lets an accountant post directly when approval is not required', function () {
    $company = makeCompany(); // require_approval = false (Dari default)
    $accountant = makeUserWithRole($company, CompanyRole::Accountant);

    $entry = postEntry($company, '2026-06-15', [
        ['account_id' => account($company, '1120')->id, 'debit' => 100],
        ['account_id' => account($company, '3100')->id, 'credit' => 100],
    ], $accountant);

    expect($entry->status->value)->toBe('posted');
});

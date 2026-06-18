<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\JournalStatus;
use App\Filament\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Services\Banking\BankStatementImporter;
use App\Support\Rbac\RbacRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->bankGl = account($this->company, '1110'); // cash/bank GL account
    $this->bank = BankAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_id' => $this->bankGl->id,
        'bank_name' => 'BPI',
        'account_no' => '1234',
    ]);
    $this->actor = makeUserWithRole($this->company, CompanyRole::Accountant);
});

it('imports a CSV statement into staging lines', function () {
    $csv = "date,description,amount,reference\n"
        .'2026-03-15,Customer deposit,"1,500.00",REF1'."\n"
        .'2026-03-16,Bank service fee,-50.00,FEE'."\n\n";

    $result = app(BankStatementImporter::class)->import($this->bank, $csv, 'mar.csv');

    expect($result)->toBe(['imported' => 2, 'skipped' => 0]);

    $lines = BankStatementLine::query()->where('bank_account_id', $this->bank->id)->orderBy('txn_date')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->amount)->toBe(1500_00)   // ₱1,500.00 deposit
        ->and($lines[1]->amount)->toBe(-50_00);   // ₱50.00 charge
});

it('posts a deposit line to the ledger and marks it matched', function () {
    $contra = account($this->company, '4100');
    $line = BankStatementLine::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'txn_date' => '2026-03-15',
        'amount' => 1500_00,
        'description' => 'Customer deposit',
    ]);

    $entry = app(BankStatementImporter::class)->recordInLedger($line, $contra->id, $this->actor);

    expect($entry->status)->toBe(JournalStatus::Posted)
        ->and($line->fresh()->status)->toBe('matched')
        ->and($line->fresh()->journal_entry_id)->toBe($entry->id)
        ->and($entry->lines->firstWhere('account_id', $this->bankGl->id)->debit->minor)->toBe(1500_00);
});

it('posts a negative line as a bank charge', function () {
    $expense = account($this->company, '6300');
    $line = BankStatementLine::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'txn_date' => '2026-03-16',
        'amount' => -50_00,
        'description' => 'Service fee',
    ]);

    $entry = app(BankStatementImporter::class)->recordInLedger($line, $expense->id, $this->actor);

    expect($entry->lines->firstWhere('account_id', $expense->id)->debit->minor)->toBe(50_00)
        ->and($entry->lines->firstWhere('account_id', $this->bankGl->id)->credit->minor)->toBe(50_00);
});

it('gates bank statements to roles with bank.reconcile', function () {
    $viewer = makeUserWithRole($this->company, CompanyRole::Viewer);

    expect($this->actor->hasCompanyPermission($this->company->id, RbacRegistry::BANK_RECONCILE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($this->company->id, RbacRegistry::BANK_RECONCILE))->toBeFalse();
});

it('renders the bank statements list page', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    Livewire::test(ListBankStatementLines::class)->assertOk();
});

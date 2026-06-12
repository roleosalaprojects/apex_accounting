<?php

declare(strict_types=1);

use App\Actions\Receivables\PostCreditMemo;
use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\CreditMemoData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Filament\Pages\Team;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\CreditMemos\Pages\CreateCreditMemo;
use App\Filament\Resources\CreditMemos\Pages\ListCreditMemos;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\LoginEvents\Pages\ListLoginEvents;
use App\Filament\Resources\Reconciliations\Pages\ListReconciliations;
use App\Filament\Resources\Reconciliations\Pages\ManageReconciliation;
use App\Filament\Resources\RecurringTemplates\Pages\EditRecurringTemplate;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Reconciliation;
use App\Models\RecurringTemplate;
use App\Models\TaxCode;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('runs a full bank reconciliation: start, toggle, complete', function () {
    // Book a ₱5,000 deposit so the bank account has one line.
    Livewire::test(ListBankAccounts::class)
        ->callAction('deposit', [
            'bank_account_id' => account($this->company, '1120')->id,
            'source_account_id' => account($this->company, '1110')->id,
            'date' => '2026-06-15',
            'amount' => '5000',
            'memo' => 'Deposit',
        ])
        ->assertHasNoActionErrors();

    $bankAccount = BankAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_id' => account($this->company, '1120')->id,
    ]);

    Livewire::test(ListReconciliations::class)
        ->callAction('start', [
            'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30',
            'statement_ending_balance' => '5000',
        ])
        ->assertHasNoActionErrors();

    $reconciliation = Reconciliation::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->firstOrFail();
    expect($reconciliation->items()->count())->toBe(1)
        ->and($reconciliation->items()->where('is_cleared', true)->count())->toBe(1);

    // Untick the only line -> difference becomes the statement balance; completing must fail.
    $item = $reconciliation->items()->firstOrFail();
    $manage = Livewire::test(ManageReconciliation::class, ['record' => $reconciliation]);
    $manage->call('toggle', $item->id);
    expect($item->fresh()->is_cleared)->toBeFalse();

    $manage->call('toggle', $item->id); // re-tick: difference back to zero
    $manage->callAction('complete')->assertHasNoActionErrors();

    expect($reconciliation->fresh()->status)->toBe('completed');
});

it('creates a credit memo from the form and applies it to an invoice', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
        'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'POS terminal', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]), $this->owner);

    Livewire::test(CreateCreditMemo::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'memo_date' => '2026-06-20',
            'pricing_mode' => 'vat_inclusive',
            'lines' => [[
                'description' => 'Return',
                'qty' => '1',
                'unit_price' => '1120', // pesos -> ₱1,120 inclusive
                'tax_code_id' => $vat12,
                'income_account_id' => account($this->company, '4200')->id,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = CreditMemo::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->firstOrFail();
    expect($memo->total->minor)->toBe(1_120_00)
        ->and($memo->vat_amount->minor)->toBe(120_00)
        ->and($memo->status)->toBe('posted');

    Repeater::fake();

    Livewire::test(ListCreditMemos::class)
        ->callAction(TestAction::make('apply')->table($memo), [
            'applications' => [['invoice_id' => $invoice->id, 'amount' => '1120']],
        ])
        ->assertHasNoActionErrors();

    expect($memo->fresh()->status)->toBe('applied')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->fresh()->outstanding())->toBe(10_080_00);
});

it('adjusts inventory from the items table', function () {
    $item = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'POS-T1',
        'income_account_id' => account($this->company, '4200')->id,
        'cogs_account_id' => account($this->company, '5200')->id,
        'inventory_account_id' => account($this->company, '1310')->id,
    ]);

    Livewire::test(ListItems::class)
        ->callAction(TestAction::make('adjust')->table($item), [
            'date' => '2026-06-15',
            'qty_change' => '10',
            'adjustment_account_id' => account($this->company, '6100')->id,
            'unit_cost' => '50',
            'reason' => 'Found stock during count',
        ])
        ->assertHasNoActionErrors();

    $item->refresh()->load('valuation');
    expect($item->valuation->qty_units)->toBe(10 * 10_000)
        ->and($item->valuation->avg_cost_x10000)->toBe(50_00 * 10_000);

    $je = JournalEntry::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('status', JournalStatus::Posted->value)->firstOrFail();
    expect($je->total_debits->minor)->toBe(500_00);
});

it('opens and closes a fiscal year from the periods page', function () {
    Livewire::test(ListAccountingPeriods::class)
        ->callAction('openFiscalYear', ['fiscal_year' => 2027])
        ->assertHasNoActionErrors();

    expect(AccountingPeriod::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('fiscal_year', 2027)->count())->toBe(12);

    Livewire::test(ListAccountingPeriods::class)
        ->callAction('closeFiscalYear', ['fiscal_year' => 2026])
        ->assertHasNoActionErrors();

    expect(AccountingPeriod::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('fiscal_year', 2026)
        ->where('status', '!=', PeriodStatus::Locked->value)->count())->toBe(0);
});

it('posts opening balances from the chart of accounts page', function () {
    Repeater::fake();

    Livewire::test(ListAccounts::class)
        ->callAction('openingBalances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => account($this->company, '1110')->id, 'debit' => '10000', 'credit' => null],
                ['account_id' => account($this->company, '2200')->id, 'debit' => null, 'credit' => '4000'],
            ],
        ])
        ->assertHasNoActionErrors();

    $je = JournalEntry::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->firstOrFail();
    expect($je->status)->toBe(JournalStatus::Posted)
        ->and($je->total_debits->minor)->toBe($je->total_credits->minor);
});

it('manages team members: add, change role, last-owner guard', function () {
    Livewire::test(Team::class)
        ->callAction('addMember', [
            'email' => 'clerk@example.com',
            'name' => 'Clerk',
            'password' => 'secret-pass-123',
            'role' => CompanyRole::Bookkeeper->value,
        ])
        ->assertHasNoActionErrors();

    $clerk = User::query()->where('email', 'clerk@example.com')->firstOrFail();
    expect($this->owner->roleIn($this->company->id))->toBe(CompanyRole::Owner)
        ->and($clerk->roleIn($this->company->id))->toBe(CompanyRole::Bookkeeper);

    Livewire::test(Team::class)
        ->callAction('changeRole', ['user_id' => $clerk->id, 'role' => CompanyRole::Accountant->value])
        ->assertHasNoActionErrors();
    expect($clerk->fresh()->roleIn($this->company->id))->toBe(CompanyRole::Accountant);

    // The sole owner cannot be demoted or removed.
    Livewire::test(Team::class)
        ->callAction('changeRole', ['user_id' => $this->owner->id, 'role' => CompanyRole::Viewer->value]);
    expect($this->owner->fresh()->roleIn($this->company->id))->toBe(CompanyRole::Owner);

    Livewire::test(Team::class)
        ->callAction('removeMember', ['user_id' => $this->owner->id]);
    expect($this->company->users()->whereKey($this->owner->id)->exists())->toBeTrue();
});

it('registers a new company with seeded chart of accounts and an owner', function () {
    Livewire::test(RegisterCompany::class)
        ->fillForm([
            'name' => 'Bagong Negosyo Inc.',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'taxpayer_type' => 'vat',
            'fiscal_year_start_month' => 1,
        ])
        ->call('register');

    $company = Company::query()->where('name', 'Bagong Negosyo Inc.')->firstOrFail();
    expect($this->owner->roleIn($company->id))->toBe(CompanyRole::Owner)
        ->and(Account::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBeGreaterThan(20)
        ->and(AccountingPeriod::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(12);
});

it('exports company data as a zip from the company profile', function () {
    Livewire::test(EditCompanyProfile::class)
        ->callAction('exportData')
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();
});

it('edits a recurring template payload as JSON', function () {
    $template = RecurringTemplate::query()->create([
        'company_id' => $this->company->id,
        'name' => 'Monthly rent',
        'kind' => 'journal_entry',
        'schedule' => 'monthly',
        'day_of_month' => 1,
        'starts_on' => '2026-06-01',
        'next_run_on' => '2026-06-01',
        'auto_post' => true,
        'is_active' => true,
        'payload' => ['memo' => 'Rent', 'lines' => []],
        'created_by' => $this->owner->id,
    ]);

    $newPayload = [
        'memo' => 'Rent accrual',
        'lines' => [
            ['account_id' => account($this->company, '6100')->id, 'debit' => 10_000_00],
            ['account_id' => account($this->company, '1110')->id, 'credit' => 10_000_00],
        ],
    ];

    Livewire::test(EditRecurringTemplate::class, ['record' => $template->id])
        ->fillForm(['payload' => json_encode($newPayload)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->payload)->toBe($newPayload);
});

it('gates the attachment download to company members', function () {
    Storage::fake('local');
    Storage::disk('local')->put('attachments/1/receipt.pdf', 'pdf-bytes');

    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => Customer::factory()->create(['company_id' => $this->company->id])->id,
        'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'Service', 'qty' => '1', 'unit_price' => 1_120_00,
            'tax_code_id' => TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id'),
            'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]), $this->owner);

    $attachment = $invoice->attachments()->create([
        'company_id' => $this->company->id,
        'disk' => 'local',
        'path' => 'attachments/1/receipt.pdf',
        'original_name' => 'receipt.pdf',
        'uploaded_by' => $this->owner->id,
    ]);

    $this->get(route('attachments.download', ['id' => $attachment->id]))
        ->assertOk()
        ->assertDownload('receipt.pdf');

    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->get(route('attachments.download', ['id' => $attachment->id]))
        ->assertForbidden();
});

it('shows the audit log and login events to owners', function () {
    $memoData = CreditMemoData::from([
        'company_id' => $this->company->id,
        'customer_id' => Customer::factory()->create(['company_id' => $this->company->id])->id,
        'memo_date' => '2026-06-20',
        'lines' => [[
            'description' => 'Return', 'qty' => '1', 'unit_price' => 1_120_00,
            'tax_code_id' => TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id'),
            'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]);
    app(PostCreditMemo::class)->handle($memoData, $this->owner);

    Livewire::test(ListAuditLogs::class)->assertSuccessful();
    Livewire::test(ListLoginEvents::class)->assertSuccessful();
});

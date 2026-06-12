<?php

declare(strict_types=1);

use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

// Strict mode (Model::shouldBeStrict) forbids lazy loading, so these pages only
// render if every relation the infolists touch is eager-loaded by the page.

it('renders the invoice view page with line and customer details', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Dari Ventures']);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [['description' => 'Consulting', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $vat12, 'income_account_id' => account($this->company, '4200')->id]],
    ]));

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertOk()
        ->assertSee('Dari Ventures')
        ->assertSee('Consulting')
        ->assertSee('VAT12');
});

it('renders the bill view page with line and vendor details', function () {
    $vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Leteres Supplies']);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    $bill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'Rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => $vat12,
            'vat_bucket' => 'common', 'expense_or_asset_account_id' => account($this->company, '6100')->id]],
    ]));

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->assertOk()
        ->assertSee('Leteres Supplies')
        ->assertSee('Rent');
});

it('renders the journal entry view page with account codes on every line', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [['description' => 'Consulting', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $vat12, 'income_account_id' => account($this->company, '4200')->id]],
    ]));

    $je = JournalEntry::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->firstOrFail();

    Livewire::test(ViewJournalEntry::class, ['record' => $je->getRouteKey()])
        ->assertOk()
        ->assertSee(account($this->company, '1200')->name)
        ->assertSee(account($this->company, '4200')->name);
});

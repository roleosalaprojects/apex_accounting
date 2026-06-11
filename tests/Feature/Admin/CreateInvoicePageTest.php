<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxCode;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('issues an invoice through the Filament create page', function () {
    $company = makeCompany();
    $owner = makeUserWithRole($company, CompanyRole::Owner);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $vat12 = TaxCode::query()->where('company_id', $company->id)->where('code', 'VAT12')->value('id');

    $this->actingAs($owner);
    Filament::setTenant($company);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'invoice_date' => '2026-06-15',
            'pricing_mode' => 'vat_inclusive',
            'lines' => [
                [
                    'description' => 'POS Terminal',
                    'qty' => '2',
                    'unit_price' => '56000',
                    'tax_code_id' => $vat12,
                    'income_account_id' => account($company, '4200')->id,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Posted)
        ->and($invoice->vatable_sales->minor)->toBe(100_000_00)
        ->and($invoice->vat_amount->minor)->toBe(12_000_00)
        ->and($invoice->total->minor)->toBe(112_000_00)
        ->and($invoice->journal_entry_id)->not->toBeNull();
});

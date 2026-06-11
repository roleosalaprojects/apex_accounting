<?php

declare(strict_types=1);

use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\CustomerPayments\Pages\CreateCustomerPayment;
use App\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Filament\Resources\VendorPayments\Pages\CreateVendorPayment;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\WithholdingCode;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('posts a balanced manual journal entry from the UI', function () {
    Livewire::test(CreateJournalEntry::class)
        ->fillForm([
            'entry_date' => '2026-06-15',
            'memo' => 'Owner capital',
            'lines' => [
                ['account_id' => account($this->company, '1120')->id, 'debit' => '1000000', 'credit' => '0'],
                ['account_id' => account($this->company, '3100')->id, 'debit' => '0', 'credit' => '1000000'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $je = JournalEntry::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($je->status->value)->toBe('posted')
        ->and($je->total_debits->minor)->toBe(1_000_000_00);
});

it('receives a customer payment and marks the invoice paid', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    $invoice = app(PostInvoice::class)->handle(
        InvoiceData::from([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-06-10',
            'lines' => [['description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
                'tax_code_id' => $vat12, 'income_account_id' => account($this->company, '4200')->id]],
        ])
    );

    Livewire::test(CreateCustomerPayment::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'payment_date' => '2026-06-15',
            'method' => 'bank',
            'deposit_to_account_id' => account($this->company, '1120')->id,
            'amount' => '11200',
            'ewt_withheld' => '0',
            'applications' => [['invoice_id' => $invoice->id, 'amount' => '11200']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('pays a bill with EWT from the UI', function () {
    $wc100 = WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC100')->value('id');
    $vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'default_withholding_code_id' => $wc100]);

    $bill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'Rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id'),
            'vat_bucket' => 'common', 'expense_or_asset_account_id' => account($this->company, '6100')->id]],
    ]));

    Livewire::test(CreateVendorPayment::class)
        ->fillForm([
            'vendor_id' => $vendor->id,
            'payment_date' => '2026-06-20',
            'method' => 'check',
            'paid_from_account_id' => account($this->company, '1120')->id,
            'applications' => [['bill_id' => $bill->id, 'amount' => '56000']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = VendorPayment::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($payment->ewt->minor)->toBe(2_500_00)
        ->and($payment->net_paid->minor)->toBe(53_500_00)
        ->and($bill->fresh()->status->value)->toBe('paid');
});

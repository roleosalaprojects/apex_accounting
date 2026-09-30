<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use App\Filament\Resources\DocumentSequences\Pages\EditDocumentSequence;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
    Repeater::fake();

    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

it('numbers purchase and sales orders from their own series', function () {
    $vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);

    foreach (['2026-06-01', '2026-06-02'] as $date) {
        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm(['vendor_id' => $vendor->id, 'order_date' => $date, 'pricing_mode' => 'vat_exclusive', 'status' => 'draft',
                'lines' => [['description' => 'Sacks', 'qty' => '10', 'unit_price' => '2000', 'tax_code_id' => $this->exempt,
                    'expense_account_id' => account($this->company, '6300')->id]]])
            ->call('create')
            ->assertHasNoFormErrors();
    }
    Livewire::test(CreateSalesOrder::class)
        ->fillForm(['customer_id' => $customer->id, 'order_date' => '2026-06-03', 'pricing_mode' => 'vat_inclusive', 'status' => 'draft',
            'lines' => [['description' => 'Terminals', 'qty' => '1', 'unit_price' => '56000', 'tax_code_id' => $this->exempt,
                'income_account_id' => account($this->company, '4200')->id]]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PurchaseOrder::query()->orderBy('id')->pluck('number')->all())->toBe(['PO-2026-000001', 'PO-2026-000002'])
        ->and(SalesOrder::query()->sole()->number)->toBe('SO-2026-000001');
});

it('issues a collection receipt number with every payment unless one is given', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [['description' => 'Rice', 'qty' => '10', 'unit_price' => 2_500_00, 'tax_code_id' => $this->exempt,
            'income_account_id' => account($this->company, '4100')->id]],
    ]));
    $collect = fn (int $amount, ?string $receipt) => app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id, 'customer_id' => $customer->id, 'payment_date' => '2026-06-15',
        'deposit_to_account_id' => account($this->company, '1120')->id, 'amount' => $amount,
        'collection_receipt_no' => $receipt,
        'applications' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
    ]));

    expect($collect(10_000_00, null)->collection_receipt_no)->toBe('CR-2026-000001')
        ->and($collect(5_000_00, 'OR-0001234')->collection_receipt_no)->toBe('OR-0001234')
        ->and($collect(5_000_00, null)->collection_receipt_no)->toBe('CR-2026-000002');
});

it('lets the owner adjust a series, but never move it backwards', function () {
    $invoices = DocumentSequence::query()->where('key', 'invoice')->sole();
    $next = $invoices->next_number;

    Livewire::test(EditDocumentSequence::class, ['record' => $invoices->getRouteKey()])
        ->assertFormSet(['key' => 'Sales invoices', 'prefix' => 'INV'])
        ->fillForm(['next_number' => $next - 1])
        ->call('save')
        ->assertHasFormErrors(['next_number'])
        ->fillForm(['prefix' => 'SI', 'next_number' => $next + 100, 'padding' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    $invoices->refresh();
    expect($invoices->prefix)->toBe('SI')
        ->and($invoices->next_number)->toBe($next + 100)
        ->and(DocumentSequenceResource::preview($invoices))->toBe('SI-'.now()->year.'-'.str_pad((string) ($next + 100), 5, '0', STR_PAD_LEFT));
});

it('keeps the series out of reach of everyone but company managers', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Accountant));

    expect(DocumentSequenceResource::canViewAny())->toBeFalse()
        ->and(DocumentSequenceResource::canCreate())->toBeFalse();
});

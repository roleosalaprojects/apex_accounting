<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Services\Sales\SalesOrderService;
use App\Support\Rbac\RbacRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->actor = makeUserWithRole($this->company, CompanyRole::Accountant);
    $this->taxCode = TaxCode::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('code', 'VAT12')->firstOrFail();
    $this->income = account($this->company, '4100');
});

function makeSalesOrderWithLine($test): SalesOrder
{
    $so = SalesOrder::factory()->create([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'order_date' => '2026-03-01',
        'pricing_mode' => 'vat_inclusive',
    ]);
    $so->lines()->create([
        'description' => 'Consulting',
        'qty' => '2',
        'unit_price' => 1120_00,
        'tax_code_id' => $test->taxCode->id,
        'income_account_id' => $test->income->id,
    ]);

    return $so->fresh();
}

it('converts a sales order into a posted invoice', function () {
    $so = makeSalesOrderWithLine($this);

    $invoice = app(SalesOrderService::class)->convertToInvoice($so, $this->actor);

    expect($invoice->status)->toBe(InvoiceStatus::Posted)
        ->and($invoice->total->minor)->toBe(2240_00)   // 2 × ₱1,120.00 (VAT-inclusive)
        ->and($so->fresh()->invoice_id)->toBe($invoice->id)
        ->and($so->fresh()->status)->toBe('invoiced');
});

it('refuses to invoice the same order twice', function () {
    $so = makeSalesOrderWithLine($this);
    app(SalesOrderService::class)->convertToInvoice($so, $this->actor);

    expect(fn () => app(SalesOrderService::class)->convertToInvoice($so->fresh(), $this->actor))
        ->toThrow(RuntimeException::class);
});

it('gates sales orders to invoice.manage roles', function () {
    $viewer = makeUserWithRole($this->company, CompanyRole::Viewer);

    expect($this->actor->hasCompanyPermission($this->company->id, RbacRegistry::INVOICE_MANAGE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($this->company->id, RbacRegistry::INVOICE_MANAGE))->toBeFalse();
});

it('renders the sales orders list page', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    Livewire::test(ListSalesOrders::class)->assertOk();
});

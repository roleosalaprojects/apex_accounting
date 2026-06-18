<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\PurchaseOrder;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Rbac\RbacRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->actor = makeUserWithRole($this->company, CompanyRole::Accountant);
    $this->taxCode = TaxCode::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('code', 'EXEMPT')->firstOrFail();
    $this->expense = account($this->company, '6300');
});

function makePurchaseOrderWithLine($test): PurchaseOrder
{
    $po = PurchaseOrder::factory()->create([
        'company_id' => $test->company->id,
        'vendor_id' => $test->vendor->id,
        'order_date' => '2026-03-01',
        'pricing_mode' => 'vat_exclusive',
    ]);
    $po->lines()->create([
        'description' => 'Office supplies',
        'qty' => '1',
        'unit_price' => 1000_00,
        'tax_code_id' => $test->taxCode->id,
        'expense_account_id' => $test->expense->id,
    ]);

    return $po->fresh();
}

it('converts a purchase order into a posted bill', function () {
    $po = makePurchaseOrderWithLine($this);

    $bill = app(PurchaseOrderService::class)->convertToBill($po, $this->actor);

    expect($bill->status)->toBe(InvoiceStatus::Posted)
        ->and($bill->total->minor)->toBe(1000_00)   // EXEMPT → no VAT added
        ->and($po->fresh()->bill_id)->toBe($bill->id)
        ->and($po->fresh()->status)->toBe('billed');
});

it('refuses to bill the same order twice', function () {
    $po = makePurchaseOrderWithLine($this);
    app(PurchaseOrderService::class)->convertToBill($po, $this->actor);

    expect(fn () => app(PurchaseOrderService::class)->convertToBill($po->fresh(), $this->actor))
        ->toThrow(RuntimeException::class);
});

it('gates purchase orders to bill.manage roles', function () {
    $viewer = makeUserWithRole($this->company, CompanyRole::Viewer);

    expect($this->actor->hasCompanyPermission($this->company->id, RbacRegistry::BILL_MANAGE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($this->company->id, RbacRegistry::BILL_MANAGE))->toBeFalse();
});

it('renders the purchase orders list page', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    Livewire::test(ListPurchaseOrders::class)->assertOk();
});

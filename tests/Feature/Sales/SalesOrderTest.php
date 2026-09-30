<?php

declare(strict_types=1);

use App\Actions\Receivables\VoidInvoice;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Services\Printing\PrintDeliveryReceipt;
use App\Services\Printing\PrintOrder;
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

/** An accepted order for 10 sacks at ₱1,120 and 2 hours of consulting at ₱1,120, VAT-inclusive. */
function makeAcceptedOrder($test): SalesOrder
{
    $so = SalesOrder::factory()->create([
        'company_id' => $test->company->id, 'customer_id' => $test->customer->id,
        'order_date' => '2026-03-01', 'pricing_mode' => 'vat_inclusive', 'status' => 'accepted',
    ]);
    $so->lines()->create(['description' => 'Rice 25kg', 'qty' => '10', 'unit_price' => 1120_00, 'tax_code_id' => $test->taxCode->id, 'income_account_id' => $test->income->id]);
    $so->lines()->create(['description' => 'Consulting', 'qty' => '2', 'unit_price' => 1120_00, 'tax_code_id' => $test->taxCode->id, 'income_account_id' => $test->income->id]);

    return $so->fresh();
}

it('delivers an order in parts on numbered delivery receipts and invoices what was delivered', function () {
    $so = makeAcceptedOrder($this);
    [$rice, $consulting] = $so->lines->all();
    $service = app(SalesOrderService::class);

    $first = $service->deliver($so, [$rice->id => '6'], '2026-03-03', 'J. Cruz', null, $this->actor);
    expect($first->number)->toBe('DR-2026-000001')
        ->and($first->lines)->toHaveCount(1)
        ->and($rice->fresh()->delivered_qty)->toBe('6.0000')
        ->and($service->suggestedInvoiceQuantities($so->fresh()))->toBe([$rice->id => '6']);

    // Nothing is delivered twice: 4 sacks remain.
    expect(fn () => $service->deliver($so->fresh(), [$rice->id => '5'], '2026-03-04'))->toThrow(RuntimeException::class, 'only 4');

    $invoice = $service->invoice($so->fresh(), [$rice->id => '6'], $this->actor, '2026-03-03');
    expect($invoice->total->minor)->toBe(6 * 1120_00)
        ->and($invoice->sales_order_id)->toBe($so->id)
        ->and($rice->fresh()->invoiced_qty)->toBe('6.0000')
        ->and($so->fresh()->status)->toBe('partially_invoiced');

    // The rest: 4 sacks and the consulting, on the second DR and the final invoice.
    $service->deliver($so->fresh(), [$rice->id => '4', $consulting->id => '2'], '2026-03-10');
    expect($service->suggestedInvoiceQuantities($so->fresh()))->toBe([$rice->id => '4', $consulting->id => '2']);
    $final = $service->convertToInvoice($so->fresh(), $this->actor);

    expect($final->total->minor)->toBe(6 * 1120_00)
        ->and($so->fresh()->status)->toBe('invoiced')
        ->and($so->fresh()->invoices()->count())->toBe(2)
        ->and($so->fresh()->deliveries()->count())->toBe(2)
        ->and(fn () => $service->invoice($so->fresh(), [$rice->id => '1'], $this->actor))->toThrow(RuntimeException::class, 'only 0');
});

it('refuses deliveries and invoices on cancelled orders, and quantities that are not on the order', function () {
    $so = makeAcceptedOrder($this);
    $line = $so->lines->first();
    $service = app(SalesOrderService::class);

    expect(fn () => $service->deliver($so, [999 => '1'], '2026-03-03'))->toThrow(RuntimeException::class, 'not on this order')
        ->and(fn () => $service->deliver($so, [$line->id => '0'], '2026-03-03'))->toThrow(RuntimeException::class, 'at least one line');

    $so->update(['status' => 'cancelled']);
    expect(fn () => $service->deliver($so->fresh(), [$line->id => '1'], '2026-03-03'))->toThrow(RuntimeException::class, 'cancelled')
        ->and(fn () => $service->invoice($so->fresh(), [$line->id => '1'], $this->actor))->toThrow(RuntimeException::class, 'cancelled');
});

it('prints a draft or sent order as a quotation and a delivery as a delivery receipt', function () {
    $so = makeAcceptedOrder($this);
    $so->update(['status' => 'sent', 'expiry_date' => '2026-03-31']);
    $html = view('print.order', app(PrintOrder::class)->data($so->fresh(), app(PrintOrder::class)->salesOrderOptions($so->fresh())))->render();
    expect($html)->toContain('QUOTATION')->toContain('Valid until')->toContain('Mar 31, 2026');

    $so->update(['status' => 'accepted']);
    expect(view('print.order', app(PrintOrder::class)->data($so->fresh(), app(PrintOrder::class)->salesOrderOptions($so->fresh())))->render())
        ->toContain('SALES ORDER')->not->toContain('QUOTATION');

    $delivery = app(SalesOrderService::class)->deliver($so->fresh(), [$so->lines->first()->id => '3'], '2026-03-05', 'J. Cruz');
    $html = view('print.delivery', app(PrintDeliveryReceipt::class)->data($delivery))->render();
    expect($html)->toContain('DELIVERY RECEIPT')->toContain('DR-2026-000001')->toContain('Rice 25kg')->toContain('J. Cruz')->toContain($so->number);
    expect(str_starts_with(app(PrintDeliveryReceipt::class)->render($delivery), '%PDF'))->toBeTrue();
});

it('delivers and invoices from the order page, then locks the order from editing', function () {
    $so = makeAcceptedOrder($this);
    [$rice, $consulting] = $so->lines->all();
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    Livewire::test(ViewSalesOrder::class, ['record' => $so->getRouteKey()])
        ->assertSee($so->number)
        ->callAction('deliver', ['delivery_date' => '2026-03-03', 'received_by' => 'J. Cruz', 'qty' => [$rice->id => '6', $consulting->id => '']])
        ->assertNotified('Delivery receipt DR-2026-000001 issued');

    expect($rice->fresh()->delivered_qty)->toBe('6.0000')
        ->and(SalesOrderResource::canEdit($so->fresh()))->toBeFalse();

    Livewire::test(ViewSalesOrder::class, ['record' => $so->getRouteKey()])
        ->assertActionHidden('edit')
        ->mountAction('invoice')
        ->assertActionDataSet(['qty' => [$rice->id => 6.0, $consulting->id => null]])
        ->callMountedAction()
        ->assertNotified('Invoiced as INV-2026-000001');

    $so->refresh();
    expect($so->status)->toBe('partially_invoiced')
        ->and($so->invoices()->sole()->total->minor)->toBe(6 * 1120_00);

    Livewire::test(ListSalesOrders::class)->assertSee('6 / 12 · 6 / 12')->assertSee('Partially invoiced');
});

it('gives the quantities back to the order when an invoice raised from it is voided', function () {
    $so = makeAcceptedOrder($this);
    [$rice, $consulting] = $so->lines->all();
    $service = app(SalesOrderService::class);

    $invoice = $service->invoice($so, [$rice->id => '6'], $this->actor, '2026-03-03');
    $service->invoice($so->fresh(), [$rice->id => '4', $consulting->id => '2'], $this->actor, '2026-03-10');
    expect($so->fresh()->status)->toBe('invoiced');

    app(VoidInvoice::class)->handle($invoice, 'Wrong price', $this->actor);

    expect($rice->fresh()->invoiced_qty)->toBe('4.0000')
        ->and($so->fresh()->status)->toBe('partially_invoiced')
        ->and($service->suggestedInvoiceQuantities($so->fresh()))->toBe([$rice->id => '6']);
});

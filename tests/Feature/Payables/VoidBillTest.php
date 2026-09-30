<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Payables\VoidBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\NegativeInventoryException;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Reports\PurchaseBook;
use App\Services\Reports\VendorSummary;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Rice Trader']);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'type' => ItemType::Inventory, 'is_vat_exempt_item' => true,
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
});

function riceReceipt(string $date, string $qty, int $price): Bill
{
    return app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id, 'vendor_id' => test()->vendor->id, 'bill_date' => $date,
        'lines' => [['description' => 'Rice', 'qty' => $qty, 'unit_price' => $price, 'tax_code_id' => test()->exempt,
            'item_id' => test()->rice->id, 'expense_or_asset_account_id' => account(test()->company, '1300')->id]],
    ]));
}

function balanceOf(string $code): int
{
    return (int) DB::table('journal_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', test()->company->id)
        ->where('journal_lines.account_id', account(test()->company, $code)->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as b')->value('b');
}

it('voids a posted bill: reverses its entry and takes the stock back at its cost', function () {
    $first = riceReceipt('2026-06-02', '1000', 2_000_00);
    riceReceipt('2026-06-10', '100', 2_100_00); // the average is now ₱2,009.09, not ₱2,000

    $voided = app(VoidBill::class)->handle($first, 'Duplicate of the mill\'s statement', $this->owner);

    expect($voided->status)->toBe(InvoiceStatus::Voided)
        ->and($first->fresh()->journalEntry->status)->toBe(JournalStatus::Reversed)
        ->and(app(InventoryService::class)->onHand($this->rice))->toMatchArray(['qty_units' => 100 * 10_000, 'value' => 210_000_00])
        ->and(balanceOf('1300'))->toBe(210_000_00)
        ->and(balanceOf('2100'))->toBe(-210_000_00)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);

    // Gone from the books and the vendor's balance.
    expect(app(PurchaseBook::class)->build($this->company->id, '2026-06-01', '2026-06-30')['totals']['total'])->toBe(210_000_00)
        ->and(app(VendorSummary::class)->build($this->vendor, '2026-01-01', '2026-06-30'))->toMatchArray(['balance' => 210_000_00, 'open_bills' => 1]);
});

it('refuses to void a bill that has been paid, even in part', function () {
    $bill = riceReceipt('2026-06-02', '1000', 2_000_00);
    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'payment_date' => '2026-06-15',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $bill->id, 'amount' => 500_000_00]],
    ]));

    expect(fn () => app(VoidBill::class)->handle($bill->fresh(), 'Wrong vendor', $this->owner))
        ->toThrow(RuntimeException::class, 'Void its payments first.');

    expect($bill->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and(app(InventoryService::class)->onHand($this->rice)['qty_units'])->toBe(1000 * 10_000);
});

it('refuses to void a receipt whose stock has already been sold', function () {
    $bill = riceReceipt('2026-06-02', '1000', 2_000_00);
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-06-12',
        'lines' => [['description' => 'Rice', 'qty' => '950', 'unit_price' => 2_500_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'income_account_id' => account($this->company, '4100')->id]],
    ]));

    expect(fn () => app(VoidBill::class)->handle($bill->fresh(), 'Never delivered', $this->owner))
        ->toThrow(NegativeInventoryException::class);

    expect($bill->fresh()->status)->toBe(InvoiceStatus::Posted)
        ->and(app(InventoryService::class)->onHand($this->rice)['qty_units'])->toBe(50 * 10_000);
});

it('voids a bill from its page', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
    $bill = riceReceipt('2026-06-02', '10', 2_000_00);

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->assertActionVisible('void')
        ->callAction('void', data: ['reason' => 'Entered twice'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('void')
        ->assertActionHidden('pay');

    expect($bill->fresh()->status)->toBe(InvoiceStatus::Voided);
});

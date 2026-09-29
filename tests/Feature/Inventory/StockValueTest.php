<?php

declare(strict_types=1);

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\ItemType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * Stock carries an exact value that moves by the same amounts the ledger does,
 * so the stock subledger equals its inventory account to the centavo (§9),
 * however awkward the averages.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->widget = Item::factory()->create([
        'company_id' => $this->company->id, 'type' => ItemType::Inventory, 'is_vat_exempt_item' => true,
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
});

function buyWidgets(string $date, string $qty, int $unitPrice): void
{
    app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id, 'vendor_id' => test()->vendor->id, 'bill_date' => $date,
        'lines' => [['description' => 'Widgets', 'qty' => $qty, 'unit_price' => $unitPrice, 'tax_code_id' => test()->exempt,
            'item_id' => test()->widget->id, 'expense_or_asset_account_id' => account(test()->company, '1300')->id]],
    ]));
}

function sellWidgets(string $date, string $qty): void
{
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => test()->company->id, 'customer_id' => test()->customer->id, 'invoice_date' => $date,
        'lines' => [['description' => 'Widgets', 'qty' => $qty, 'unit_price' => 5_00, 'tax_code_id' => test()->exempt,
            'item_id' => test()->widget->id, 'income_account_id' => account(test()->company, '4100')->id]],
    ]));
}

function ledgerBalance(string $code): int
{
    return (int) DB::table('journal_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', test()->company->id)
        ->where('journal_lines.account_id', account(test()->company, $code)->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as b')->value('b');
}

it('relieves the last unit at whatever value is left, so nothing is stranded', function () {
    // 3 widgets for ₱10.00 (2 at ₱3.33, 1 at ₱3.34): ₱3.3333… each. Selling them
    // one by one at a rounded average used to strand ₱0.01 with no stock left.
    buyWidgets('2026-06-01', '2', 3_33);
    buyWidgets('2026-06-01', '1', 3_34);

    foreach (['2026-06-10', '2026-06-11', '2026-06-12'] as $date) {
        sellWidgets($date, '1');

        expect(app(InventoryService::class)->onHand($this->widget)['value'])->toBe(ledgerBalance('1300'));
    }

    expect(app(InventoryService::class)->onHand($this->widget))->toMatchArray(['qty_units' => 0, 'value' => 0])
        ->and(ledgerBalance('1300'))->toBe(0)
        ->and(ledgerBalance('5100'))->toBe(10_00) // every centavo paid reached cost of sales
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('keeps stock equal to the ledger through uneven purchases, sales and counts', function () {
    $inventory = app(InventoryService::class);
    $steps = [
        fn () => buyWidgets('2026-06-01', '7', 1_43),
        fn () => sellWidgets('2026-06-02', '2.5'),
        fn () => buyWidgets('2026-06-03', '11', 1_39),
        fn () => sellWidgets('2026-06-04', '3.3333'),
        fn () => app(AdjustInventory::class)->handle($this->widget, '2026-06-05', '1.75', account($this->company, '6100')->id),
        fn () => sellWidgets('2026-06-06', '9'),
        fn () => app(AdjustInventory::class)->handle($this->widget, '2026-06-07', '-0.4167', account($this->company, '6100')->id),
        fn () => buyWidgets('2026-06-08', '0.3', 2_17),
        fn () => sellWidgets('2026-06-09', '1.2'),
    ];

    foreach ($steps as $step) {
        $step();

        expect($inventory->onHand($this->widget)['value'])->toBe(ledgerBalance('1300'));
    }

    // Sell out whatever is left: the account empties exactly.
    $left = $inventory->onHand($this->widget)['qty_units'];
    sellWidgets('2026-06-10', rtrim(rtrim(Quantity::fromUnits($left), '0'), '.'));

    expect($inventory->onHand($this->widget)['value'])->toBe(0)
        ->and(ledgerBalance('1300'))->toBe(0)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('fails ledger:verify when stock does not match its inventory account', function () {
    buyWidgets('2026-06-01', '10', 2_00);
    postEntry($this->company, '2026-06-02', [
        ['account_id' => account($this->company, '1300')->id, 'debit' => 5_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 5_00],
    ]);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(1)
        ->and(Artisan::output())->toContain('1300')->toContain('stock');
});

it('backfills existing stock value, folding in rounding but not real differences', function () {
    buyWidgets('2026-06-01', '2', 3_33);
    buyWidgets('2026-06-01', '1', 3_34);
    sellWidgets('2026-06-10', '1');
    sellWidgets('2026-06-11', '1'); // 1 widget left; the ledger holds ₱3.34 for it

    $backfill = fn () => (require database_path('migrations/2026_09_30_000001_add_value_to_item_valuations_table.php'))->backfill();

    // As in a database from before stock carried its value: implied from the
    // average (₱3.3333 → ₱3.33), plus the centavo rounding left in the ledger.
    DB::table('item_valuations')->update(['value' => 0]);
    $backfill();

    expect(app(InventoryService::class)->onHand($this->widget)['value'])->toBe(3_34)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);

    // ₱5.00 posted to inventory by hand is not rounding: it stays visible.
    postEntry($this->company, '2026-06-12', [
        ['account_id' => account($this->company, '1300')->id, 'debit' => 5_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 5_00],
    ]);
    DB::table('item_valuations')->update(['value' => 0]);
    $backfill();

    expect(app(InventoryService::class)->onHand($this->widget)['value'])->toBe(3_34)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(1);
});

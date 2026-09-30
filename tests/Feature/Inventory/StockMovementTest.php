<?php

declare(strict_types=1);

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Payables\PostBill;
use App\Actions\Payables\PostDebitMemo;
use App\Actions\Payables\VoidBill;
use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\VoidInvoice;
use App\Data\Payables\BillData;
use App\Data\Payables\DebitMemoData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\ItemType;
use App\Enums\StockMovementKind;
use App\Filament\Pages\Reports\StockCardPage;
use App\Filament\Pages\Reports\StockSummaryPage;
use App\Filament\Resources\Items\Pages\ViewItem;
use App\Models\Customer;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Reports\StockCardReport;
use App\Services\Reports\StockSummaryReport;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Every movement of stock — receipt, sale, return, count, void — leaves a row
 * in the stock ledger, so a stock card can be read back and the ledger's sum
 * always equals the running valuation.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'RICE-25', 'name' => 'Rice 25kg', 'type' => ItemType::Inventory, 'is_vat_exempt_item' => true,
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
});

function buyRice(string $date, string $qty, int $unitPrice)
{
    return app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id, 'vendor_id' => test()->vendor->id, 'bill_date' => $date,
        'lines' => [['description' => 'Rice 25kg', 'qty' => $qty, 'unit_price' => $unitPrice, 'tax_code_id' => test()->exempt,
            'item_id' => test()->rice->id, 'expense_or_asset_account_id' => account(test()->company, '1300')->id]],
    ]));
}

function sellRice(string $date, string $qty)
{
    return app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => test()->company->id, 'customer_id' => test()->customer->id, 'invoice_date' => $date,
        'lines' => [['description' => 'Rice 25kg', 'qty' => $qty, 'unit_price' => 1_500_00, 'tax_code_id' => test()->exempt,
            'item_id' => test()->rice->id, 'income_account_id' => account(test()->company, '4100')->id]],
    ]));
}

it('records every kind of stock movement and keeps their sum equal to the valuation', function () {
    $first = buyRice('2026-06-01', '10', 1_000_00);
    buyRice('2026-06-03', '10', 1_200_00);              // average now ₱1,100
    $sale = sellRice('2026-06-05', '4');                 // out at ₱1,100 → ₱4,400
    app(AdjustInventory::class)->handle($this->rice, '2026-06-07', '-1', account($this->company, '6400')->id, reason: 'spoilage', actor: $this->owner);
    $memo = app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'memo_date' => '2026-06-09',
        'lines' => [['item_id' => $this->rice->id, 'description' => 'Rice 25kg', 'qty' => '2', 'unit_price' => 1_200_00,
            'tax_code_id' => $this->exempt, 'expense_or_asset_account_id' => account($this->company, '1300')->id]],
    ]));
    app(VoidInvoice::class)->handle($sale, 'Wrong customer', $this->owner);
    app(VoidBill::class)->handle($first->fresh(), 'Duplicate', $this->owner);

    $movements = StockMovement::query()->where('item_id', $this->rice->id)->orderBy('id')->get();

    expect($movements->map(fn (StockMovement $m): array => [$m->moved_on->toDateString(), $m->kind, $m->qty_units, $m->value, $m->reference])->all())->toBe([
        ['2026-06-01', StockMovementKind::Receipt, 100_000, 10_000_00, 'BILL-2026-000001'],
        ['2026-06-03', StockMovementKind::Receipt, 100_000, 12_000_00, 'BILL-2026-000002'],
        ['2026-06-05', StockMovementKind::Issue, -40_000, -4_400_00, 'INV-2026-000001'],
        ['2026-06-07', StockMovementKind::Adjustment, -10_000, -1_100_00, null],
        ['2026-06-09', StockMovementKind::ReturnOut, -20_000, -2_200_00, 'DM-2026-000001'],
        ['2026-06-05', StockMovementKind::VoidIn, 40_000, 4_400_00, 'INV-2026-000001'],
        ['2026-06-01', StockMovementKind::VoidOut, -100_000, -10_000_00, 'BILL-2026-000001'],
    ]);

    $onHand = app(InventoryService::class)->onHand($this->rice);
    expect((int) $movements->sum('qty_units'))->toBe($onHand['qty_units'])
        ->and((int) $movements->sum('value'))->toBe($onHand['value'])
        ->and($movements->firstWhere('kind', StockMovementKind::Adjustment)->source_type)->toBe(InventoryAdjustment::class)
        ->and($movements->firstWhere('kind', StockMovementKind::Issue)->source_id)->toBe($sale->id)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('fails ledger:verify when the stock ledger no longer sums to the valuation', function () {
    buyRice('2026-06-01', '10', 1_000_00);
    DB::table('stock_movements')->where('item_id', $this->rice->id)->update(['qty_units' => 90_000]);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(1)
        ->and(Artisan::output())->toContain('RICE-25');
});

it('reads a stock card back with opening balance, running balances and closing', function () {
    buyRice('2026-05-20', '10', 1_000_00);
    sellRice('2026-05-25', '3');                 // May: 7 left at ₱1,000
    buyRice('2026-06-03', '5', 1_240_00);         // 12 on hand, value 7,000 + 6,200 = 13,200 → avg 1,100
    sellRice('2026-06-10', '2');                 // -2,200 → 10 on hand, 11,000

    $card = app(StockCardReport::class)->build($this->rice, '2026-06-01', '2026-06-30');

    expect($card['opening'])->toBe(['qty_units' => 70_000, 'value' => 7_000_00])
        ->and($card['rows'])->toHaveCount(2)
        ->and($card['rows'][0])->toMatchArray(['date' => '2026-06-03', 'kind' => 'Receipt', 'reference' => 'BILL-2026-000002', 'in_units' => 50_000, 'out_units' => 0, 'value' => 6_200_00, 'unit_cost' => 1_240_00, 'balance_units' => 120_000, 'balance_value' => 13_200_00])
        ->and($card['rows'][1])->toMatchArray(['date' => '2026-06-10', 'kind' => 'Sale', 'reference' => 'INV-2026-000002', 'in_units' => 0, 'out_units' => 20_000, 'value' => -2_200_00, 'unit_cost' => 1_100_00, 'balance_units' => 100_000, 'balance_value' => 11_000_00])
        ->and($card['closing'])->toBe(['qty_units' => 100_000, 'value' => 11_000_00]);
});

it('summarises stock for a period, item by item, tying the closing value to the ledger', function () {
    $pos = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'POS-1', 'name' => 'POS Terminal', 'type' => ItemType::Inventory,
        'income_account_id' => account($this->company, '4200')->id,
        'cogs_account_id' => account($this->company, '5200')->id,
        'inventory_account_id' => account($this->company, '1310')->id,
    ]);
    buyRice('2026-05-20', '10', 1_000_00);
    sellRice('2026-05-25', '3');
    buyRice('2026-06-03', '5', 1_240_00);
    sellRice('2026-06-10', '2');
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-15',
        'lines' => [['description' => 'POS Terminal', 'qty' => '2', 'unit_price' => 20_000_00, 'tax_code_id' => $this->exempt,
            'item_id' => $pos->id, 'expense_or_asset_account_id' => account($this->company, '1310')->id]],
    ]));

    $summary = app(StockSummaryReport::class)->build($this->company->id, '2026-06-01', '2026-06-30');

    expect($summary['rows'])->toHaveCount(2)
        ->and($summary['rows'][0])->toMatchArray(['sku' => 'POS-1', 'opening_units' => 0, 'in_units' => 20_000, 'out_units' => 0, 'closing_units' => 20_000, 'closing_value' => 40_000_00, 'avg_cost' => 20_000_00])
        ->and($summary['rows'][1])->toMatchArray(['sku' => 'RICE-25', 'opening_units' => 70_000, 'opening_value' => 7_000_00, 'in_units' => 50_000, 'in_value' => 6_200_00, 'out_units' => 20_000, 'out_value' => 2_200_00, 'closing_units' => 100_000, 'closing_value' => 11_000_00, 'avg_cost' => 1_100_00])
        ->and($summary['totals'])->toMatchArray(['opening_value' => 7_000_00, 'in_value' => 46_200_00, 'out_value' => 2_200_00, 'closing_value' => 51_000_00])
        ->and($summary['ledger_value'])->toBe(51_000_00);

    // An as-of valuation is the summary's closing column.
    $may = app(StockSummaryReport::class)->build($this->company->id, '2026-05-01', '2026-05-31');
    expect($may['rows'])->toHaveCount(1)->and($may['totals']['closing_value'])->toBe(7_000_00);
});

it('shows the stock reports, with the stock card preselected from the item page', function () {
    buyRice('2026-06-03', '5', 1_240_00);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);

    Livewire::test(StockSummaryPage::class)
        ->set('from', '2026-06-01')->set('asOf', '2026-06-30')
        ->assertSee('Rice 25kg')->assertSee('6,200.00')->assertSee('ties to the ledger');

    Livewire::withQueryParams(['item' => $this->rice->id])
        ->test(StockCardPage::class)
        ->set('from', '2026-06-01')->set('asOf', '2026-06-30')
        ->assertSet('entity', (string) $this->rice->id)
        ->assertSee('BILL-2026-000001')->assertSee('1,240.00');
});

it('links from the item page to its stock card', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);

    Livewire::test(ViewItem::class, ['record' => $this->rice->getRouteKey()])
        ->assertActionVisible('stock_card')
        ->assertActionHasUrl('stock_card', StockCardPage::getUrl(['item' => $this->rice->id]));
});

<?php

declare(strict_types=1);

use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Enums\CompanyRole;
use App\Enums\ItemType;
use App\Exceptions\Ledger\InventoryAccountException;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemValuation;
use App\Models\JournalEntry;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

/*
 * A stocked item is carried at weighted-average cost in its inventory account
 * (§9). If a bill could debit its cost anywhere else, the receipt would land in
 * the stock subledger but not in the ledger, and the two would drift apart.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Rice Trader']);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'name' => 'Kohaku Yellow 25kg', 'type' => ItemType::Inventory,
        'is_vat_exempt_item' => true, 'default_purchase_price' => 1_350_00,
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
});

function riceBill(int $accountId, array $overrides = [], ?int $itemId = null): BillData
{
    return BillData::from(array_merge([
        'company_id' => test()->company->id, 'vendor_id' => test()->vendor->id, 'bill_date' => '2026-06-30',
        'lines' => [[
            'description' => 'Rice 25kg', 'qty' => '1000', 'unit_price' => 1_310_00, 'tax_code_id' => test()->exempt,
            'item_id' => $itemId ?? test()->rice->id, 'expense_or_asset_account_id' => $accountId,
        ]],
    ], $overrides));
}

it('refuses to post a stocked item anywhere but its inventory account', function () {
    expect(fn () => app(PostBill::class)->handle(riceBill(account($this->company, '1120')->id)))
        ->toThrow(InventoryAccountException::class, 'Line 1: Kohaku Yellow 25kg is a stocked item, so its cost must go to 1300 Inventory — Rice, not 1120 Cash in Bank.');

    // Nothing half-posted: no bill, no entry, no stock.
    expect(Bill::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0)
        ->and(JournalEntry::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0)
        ->and(ItemValuation::query()->withoutGlobalScopes()->where('item_id', $this->rice->id)->value('qty_units') ?? 0)->toBe(0);
});

it('posts a stocked item to its inventory account, keeping stock equal to the ledger', function () {
    $bill = app(PostBill::class)->handle(riceBill(account($this->company, '1300')->id));

    $debit = $bill->journalEntry->lines->firstWhere('debit.minor', '>', 0);

    expect($debit->account_id)->toBe(account($this->company, '1300')->id)
        ->and($debit->debit->minor)->toBe(1_310_000_00)
        ->and(app(InventoryService::class)->onHand($this->rice)['value'])->toBe(1_310_000_00)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('refuses a stocked item that has no inventory account', function () {
    $this->rice->forceFill(['inventory_account_id' => null])->save();

    expect(fn () => app(PostBill::class)->handle(riceBill(account($this->company, '1300')->id)))
        ->toThrow(InventoryAccountException::class, 'Line 1: Kohaku Yellow 25kg is a stocked item but has no inventory account. Set one on the item first.');
});

it('lets lines for non-stock items post to any account', function () {
    $service = Item::factory()->create([
        'company_id' => $this->company->id, 'type' => ItemType::Service, 'inventory_account_id' => null,
    ]);

    $bill = app(PostBill::class)->handle(riceBill(account($this->company, '6100')->id, itemId: $service->id));

    expect($bill->journalEntry->lines->firstWhere('debit.minor', '>', 0)->account_id)->toBe(account($this->company, '6100')->id);
});

it('refuses an item that belongs to another company', function () {
    $foreign = Item::factory()->create(['company_id' => Company::factory()->create()->id, 'type' => ItemType::Inventory]);

    expect(fn () => app(PostBill::class)->handle(riceBill(account($this->company, '1300')->id, itemId: $foreign->id)))
        ->toThrow(RuntimeException::class, "Item {$foreign->id} not found.");
});

it('moves no stock on an opening-balance bill, whose entry never touches inventory', function () {
    $bill = app(PostBill::class)->handle(riceBill(account($this->company, '1300')->id, ['is_opening' => true]));

    expect($bill->journalEntry->lines->pluck('account_id')->all())->not->toContain(account($this->company, '1300')->id)
        ->and(app(InventoryService::class)->onHand($this->rice)['qty_units'])->toBe(0)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('fills in and locks the inventory account when a stocked item is picked on a bill', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
    Repeater::fake();

    Livewire::test(CreateBill::class)
        ->set('data.lines.0.item_id', $this->rice->id)
        ->assertSet('data.lines.0.expense_or_asset_account_id', account($this->company, '1300')->id)
        ->assertSet('data.lines.0.description', 'Kohaku Yellow 25kg')
        ->assertSet('data.lines.0.unit_price', '1350.00')
        ->assertFormFieldIsDisabled('lines.0.expense_or_asset_account_id');
});

it('shows why a bill was refused instead of failing', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
    Repeater::fake();

    Livewire::test(CreateBill::class)
        ->fillForm([
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-30', 'pricing_mode' => 'vat_exclusive',
            'lines' => [['item_id' => $this->rice->id, 'description' => 'Rice 25kg', 'qty' => '1000', 'unit_price' => '1310',
                'tax_code_id' => $this->exempt]],
        ])
        ->set('data.lines.0.expense_or_asset_account_id', account($this->company, '1120')->id)
        ->call('create')
        ->assertNotified('Could not post bill');

    expect(Bill::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});

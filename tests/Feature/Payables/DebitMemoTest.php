<?php

declare(strict_types=1);

use App\Actions\Payables\ApplyDebitMemo;
use App\Actions\Payables\PostBill;
use App\Actions\Payables\PostDebitMemo;
use App\Actions\Payables\VoidBill;
use App\Data\Payables\BillData;
use App\Data\Payables\DebitMemoData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\PricingMode;
use App\Enums\VatBucket;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\DebitMemos\Pages\CreateDebitMemo;
use App\Filament\Resources\DebitMemos\Pages\ListDebitMemos;
use App\Filament\Resources\DebitMemos\Pages\ViewDebitMemo;
use App\Models\Bill;
use App\Models\DebitMemo;
use App\Models\Item;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

function debitMemoBalance(string $code, int $period = 6): int
{
    $periodId = test()->company->periods()->where('period_no', $period)->value('id');

    $row = PeriodBalance::query()
        ->where('account_id', account(test()->company, $code)->id)
        ->where('period_id', $periodId)->first();

    return $row?->closing->minor ?? 0;
}

/** A ₱224,000 bill: 10 POS units at ₱20,000 + 12% VAT, direct_vatable. */
function posUnitsBill(): Bill
{
    return app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id,
        'vendor_id' => test()->vendor->id,
        'bill_date' => '2026-06-03',
        'lines' => [[
            'description' => 'POS units', 'qty' => '10', 'unit_price' => 20_000_00,
            'tax_code_id' => test()->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
            'expense_or_asset_account_id' => account(test()->company, '1310')->id,
        ]],
    ]));
}

it('posts a debit memo that debits AP and reverses the cost and input VAT, then applies it to the bill', function () {
    $bill = posUnitsBill();

    $memo = app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'memo_date' => '2026-06-10',
        'memo' => 'Two units dead on arrival',
        'lines' => [[
            'description' => 'POS units returned', 'qty' => '2', 'unit_price' => 20_000_00,
            'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
            'expense_or_asset_account_id' => account($this->company, '1310')->id,
        ]],
    ]));

    expect($memo->number)->toBe('DM-2026-000001')
        ->and($memo->status)->toBe('posted')
        ->and($memo->total->minor)->toBe(44_800_00)
        ->and($memo->vatable_purchases->minor)->toBe(40_000_00)
        ->and($memo->input_vat->minor)->toBe(4_800_00)
        ->and($memo->journal_entry_id)->not->toBeNull();

    // Bill: Dr 1310 200,000 / Dr 1400 24,000 / Cr 2100 224,000. Memo takes 2 units back out.
    expect(debitMemoBalance('1310'))->toBe(160_000_00)
        ->and(debitMemoBalance('1400'))->toBe(19_200_00)
        ->and(debitMemoBalance('2100'))->toBe(-179_200_00);

    app(ApplyDebitMemo::class)->handle($memo, [['bill_id' => $bill->id, 'amount' => 44_800_00]]);

    expect($bill->refresh()->outstanding())->toBe(179_200_00)
        ->and($bill->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($memo->refresh()->status)->toBe('applied');

    // The bill can no longer be voided while the memo is applied to it.
    expect(fn () => app(VoidBill::class)->handle($bill, 'Wrong units'))->toThrow(RuntimeException::class);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('refuses to apply more than the memo or the bill has open', function () {
    $bill = posUnitsBill();
    $memo = app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'memo_date' => '2026-06-10',
        'lines' => [[
            'description' => 'Returned', 'qty' => '1', 'unit_price' => 20_000_00,
            'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
            'expense_or_asset_account_id' => account($this->company, '1310')->id,
        ]],
    ]));

    expect(fn () => app(ApplyDebitMemo::class)->handle($memo, [['bill_id' => $bill->id, 'amount' => 22_400_01]]))
        ->toThrow(RuntimeException::class, 'exceeds');

    $other = Vendor::factory()->create(['company_id' => $this->company->id]);
    $otherBill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $other->id, 'bill_date' => '2026-06-04',
        'lines' => [['description' => 'Rice', 'qty' => '1', 'unit_price' => 1_000_00, 'tax_code_id' => $this->exempt,
            'expense_or_asset_account_id' => account($this->company, '6300')->id]],
    ]));
    expect(fn () => app(ApplyDebitMemo::class)->handle($memo, [['bill_id' => $otherBill->id, 'amount' => 1_000_00]]))
        ->toThrow(RuntimeException::class, 'vendor');
});

it('takes returned stock back out at average cost and books any price difference to cost of sales', function () {
    $rice = Item::factory()->create([
        'company_id' => $this->company->id, 'name' => 'Kohaku Yellow 25kg', 'type' => ItemType::Inventory,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
    $buy = fn (int $unitPrice) => app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-03',
        'lines' => [['item_id' => $rice->id, 'description' => 'Rice', 'qty' => '10', 'unit_price' => $unitPrice,
            'tax_code_id' => $this->exempt, 'expense_or_asset_account_id' => account($this->company, '1300')->id]],
    ]));
    $buy(1_000_00);
    $buy(1_200_00); // 20 sacks on hand, average ₱1,100

    $memo = app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'memo_date' => '2026-06-10',
        'lines' => [['item_id' => $rice->id, 'description' => 'Rice returned', 'qty' => '5', 'unit_price' => 1_200_00,
            'tax_code_id' => $this->exempt, 'expense_or_asset_account_id' => account($this->company, '1300')->id]],
    ]));

    // Vendor credits ₱6,000; stock leaves at 5 × ₱1,100 = ₱5,500; the ₱500 gap is a cost-of-sales gain.
    expect($memo->total->minor)->toBe(6_000_00)
        ->and(app(InventoryService::class)->onHand($rice))->toMatchArray(['qty_units' => 15 * 10000, 'value' => 16_500_00])
        ->and(debitMemoBalance('1300'))->toBe(16_500_00)
        ->and(debitMemoBalance('5100'))->toBe(-500_00)
        ->and(debitMemoBalance('2100'))->toBe(-16_000_00);

    // A stocked item must go back out of its inventory account, like it came in.
    expect(fn () => app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'memo_date' => '2026-06-11',
        'lines' => [['item_id' => $rice->id, 'description' => 'Rice', 'qty' => '1', 'unit_price' => 1_000_00,
            'tax_code_id' => $this->exempt, 'expense_or_asset_account_id' => account($this->company, '6300')->id]],
    ])))->toThrow(RuntimeException::class);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('posts a debit memo from the screen and applies it to the bill', function () {
    $owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($owner);
    Filament::setTenant($this->company);
    Repeater::fake();
    $bill = posUnitsBill();

    Livewire::test(CreateDebitMemo::class)
        ->fillForm([
            'vendor_id' => $this->vendor->id, 'memo_date' => '2026-06-10', 'pricing_mode' => 'vat_exclusive',
            'memo' => 'Two units dead on arrival',
            'lines' => [['description' => 'POS units returned', 'qty' => '2', 'unit_price' => '20000',
                'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
                'expense_or_asset_account_id' => account($this->company, '1310')->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = DebitMemo::query()->sole();
    expect($memo->total->minor)->toBe(44_800_00)->and($memo->status)->toBe('posted');

    Livewire::test(ViewDebitMemo::class, ['record' => $memo->getRouteKey()])
        ->assertSee('DM-2026-000001')
        ->assertSee('Two units dead on arrival')
        ->callAction('apply', ['applications' => [['bill_id' => $bill->id, 'amount' => '44800']]])
        ->assertNotified('Debit memo applied');

    expect($bill->refresh()->outstanding())->toBe(179_200_00)
        ->and($memo->refresh()->status)->toBe('applied');

    Livewire::test(ListDebitMemos::class)->assertCanSeeTableRecords([$memo])->assertSee('44,800.00');
});

it('starts a debit memo from the bill, prefilled with its lines', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
    Repeater::fake();
    $bill = posUnitsBill();

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->assertActionVisible('debit_memo');

    Livewire::withQueryParams(['bill' => $bill->id])
        ->test(CreateDebitMemo::class)
        ->assertFormSet([
            'vendor_id' => $this->vendor->id,
            'pricing_mode' => PricingMode::VatExclusive,
            'lines' => [['item_id' => null, 'description' => 'POS units', 'qty' => 10.0, 'unit_price' => 20000.0,
                'tax_code_id' => (string) $this->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
                'expense_or_asset_account_id' => (string) account($this->company, '1310')->id]],
        ]);
});

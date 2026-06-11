<?php

declare(strict_types=1);

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\VatBucket;
use App\Exceptions\Ledger\NegativeInventoryException;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');

    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'RICE-25', 'is_vat_exempt_item' => true, 'unit' => 'sack_25kg',
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
    $this->pos = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'POS-T1',
        'income_account_id' => account($this->company, '4200')->id,
        'cogs_account_id' => account($this->company, '5200')->id,
        'inventory_account_id' => account($this->company, '1310')->id,
    ]);
});

function bal(string $code): int
{
    $p6 = test()->company->periods()->where('period_no', 6)->value('id');
    $row = PeriodBalance::query()->where('account_id', account(test()->company, $code)->id)->where('period_id', $p6)->first();

    return $row?->closing->minor ?? 0;
}

it('matches the golden-master weighted-average fixture', function () {
    // B-1: 1,000 sacks rice @ ₱2,000 (exempt).
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-02',
        'lines' => [[
            'description' => 'Rice', 'qty' => '1000', 'unit_price' => 2_000_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'expense_or_asset_account_id' => account($this->company, '1300')->id,
        ]],
    ]));

    // B-2: 10 POS @ ₱20,000 + 12% (direct_vatable).
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-03',
        'lines' => [[
            'description' => 'POS', 'qty' => '10', 'unit_price' => 20_000_00, 'tax_code_id' => $this->vat12,
            'vat_bucket' => VatBucket::DirectVatable->value, 'item_id' => $this->pos->id,
            'expense_or_asset_account_id' => account($this->company, '1310')->id,
        ]],
    ]));

    expect(app(InventoryService::class)->currentQtyUnits($this->rice))->toBe(1000 * 10000)
        ->and(app(InventoryService::class)->inventoryValue($this->rice))->toBe(2_000_000_00);

    // INV-1: sell 360 sacks rice -> COGS 360 × 2,000 = ₱720,000.
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'Rice', 'qty' => '360', 'unit_price' => 2_500_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'income_account_id' => account($this->company, '4100')->id,
        ]],
    ]));

    // INV-2: sell 2 POS -> COGS 2 × 20,000 = ₱40,000.
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-06-12',
        'lines' => [[
            'description' => 'POS', 'qty' => '2', 'unit_price' => 56_000_00, 'tax_code_id' => $this->vat12,
            'item_id' => $this->pos->id, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    // Inventory and COGS tie out to the §20 figures.
    expect(bal('1300'))->toBe(1_280_000_00)   // 640 sacks @ ₱2,000
        ->and(bal('1310'))->toBe(160_000_00)  // 8 units @ ₱20,000
        ->and(bal('5100'))->toBe(720_000_00)
        ->and(bal('5200'))->toBe(40_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('recomputes the weighted average across receipts at different costs', function () {
    $inv = app(InventoryService::class);

    // Receive 100 @ ₱10 then 100 @ ₱20 -> avg ₱15.
    $inv->receive($this->pos, 100 * 10000, 1_000_00);
    $inv->receive($this->pos, 100 * 10000, 2_000_00);

    expect($inv->currentQtyUnits($this->pos))->toBe(200 * 10000)
        ->and($inv->inventoryValue($this->pos))->toBe(3_000_00)
        ->and($inv->valueAtCurrentAverage($this->pos, 10 * 10000))->toBe(150_00); // 10 @ ₱15
});

it('blocks selling more than on hand when the company flag is set', function () {
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-02',
        'lines' => [[
            'description' => 'Rice', 'qty' => '5', 'unit_price' => 2_000_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'expense_or_asset_account_id' => account($this->company, '1300')->id,
        ]],
    ]));

    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'Rice', 'qty' => '10', 'unit_price' => 2_500_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'income_account_id' => account($this->company, '4100')->id,
        ]],
    ]));
})->throws(NegativeInventoryException::class);

it('posts an inventory count adjustment', function () {
    $inv = app(InventoryService::class);

    // Seed 100 sacks @ ₱2,000 through a bill so the GL inventory account is real.
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-02',
        'lines' => [[
            'description' => 'Rice', 'qty' => '100', 'unit_price' => 2_000_00, 'tax_code_id' => $this->exempt,
            'item_id' => $this->rice->id, 'expense_or_asset_account_id' => account($this->company, '1300')->id,
        ]],
    ]));

    // Count down by 5 sacks -> ₱10,000 shrinkage.
    $adj = app(AdjustInventory::class)->handle(
        $this->rice, '2026-06-15', '-5', account($this->company, '6400')->id, reason: 'spoilage',
    );

    expect($adj->qty_units_change)->toBe(-5 * 10000)
        ->and($adj->value_change->minor)->toBe(-10_000_00)
        ->and($inv->currentQtyUnits($this->rice))->toBe(95 * 10000)
        ->and(bal('1300'))->toBe(190_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

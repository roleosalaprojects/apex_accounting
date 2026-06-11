<?php

declare(strict_types=1);

use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Enums\VatBucket;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use App\Models\Vendor;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

function billBalance(string $code, int $period = 6): int
{
    $periodId = test()->company->periods()->where('period_no', $period)->value('id');

    $row = PeriodBalance::query()
        ->where('account_id', account(test()->company, $code)->id)
        ->where('period_id', $periodId)->first();

    return $row?->closing->minor ?? 0;
}

it('posts a direct_vatable bill — input VAT to 1400 (B-2 fixture)', function () {
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'bill_date' => '2026-06-03',
        'lines' => [[
            'description' => 'POS units', 'qty' => '10', 'unit_price' => 20_000_00,
            'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::DirectVatable->value,
            'expense_or_asset_account_id' => account($this->company, '1310')->id,
        ]],
    ]));

    expect(billBalance('1310'))->toBe(200_000_00)
        ->and(billBalance('1400'))->toBe(24_000_00)
        ->and(billBalance('2100'))->toBe(-224_000_00); // AP credit balance

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('posts a common bill — input VAT deferred to 1410 (B-3 fixture)', function () {
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'bill_date' => '2026-06-05',
        'pricing_mode' => 'vat_inclusive',
        'lines' => [[
            'description' => 'Office rent', 'qty' => '1', 'unit_price' => 56_000_00,
            'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::Common->value,
            'expense_or_asset_account_id' => account($this->company, '6100')->id,
        ]],
    ]));

    expect(billBalance('6100'))->toBe(50_000_00)
        ->and(billBalance('1410'))->toBe(6_000_00)
        ->and(billBalance('2100'))->toBe(-56_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('capitalizes exempt-attributable input VAT into cost (direct_exempt)', function () {
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'bill_date' => '2026-06-05',
        'lines' => [[
            'description' => 'Rice-side supplies', 'qty' => '1', 'unit_price' => 100_000_00,
            'tax_code_id' => $this->vat12, 'vat_bucket' => VatBucket::DirectExempt->value,
            'expense_or_asset_account_id' => account($this->company, '6400')->id,
        ]],
    ]));

    // VAT (12,000) folded into cost; nothing in 1400.
    expect(billBalance('6400'))->toBe(112_000_00)
        ->and(billBalance('1400'))->toBe(0)
        ->and(billBalance('2100'))->toBe(-112_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('posts an exempt rice purchase with no VAT (B-1 fixture)', function () {
    $bill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'bill_date' => '2026-06-02',
        'lines' => [[
            'description' => 'Rice 25kg', 'qty' => '1000', 'unit_price' => 2_000_00,
            'tax_code_id' => $this->exempt,
            'expense_or_asset_account_id' => account($this->company, '1300')->id,
        ]],
    ]));

    expect($bill->exempt_purchases->minor)->toBe(2_000_000_00)
        ->and($bill->input_vat->minor)->toBe(0)
        ->and(billBalance('1300'))->toBe(2_000_000_00)
        ->and(billBalance('2100'))->toBe(-2_000_000_00);
});

it('rejects a bill line carrying input VAT without a bucket', function () {
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->vendor->id,
        'bill_date' => '2026-06-05',
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 20_000_00,
            'tax_code_id' => $this->vat12, // VAT12 but no vat_bucket
            'expense_or_asset_account_id' => account($this->company, '1310')->id,
        ]],
    ]));
})->throws(InvalidVatBucketException::class);

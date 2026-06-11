<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Enums\InvoiceStatus;
use App\Enums\VatBucket;
use App\Models\Bill;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\WithholdingCode;
use App\Models\WithholdingTransaction;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->wc100 = WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC100')->value('id');
    $this->landlord = Vendor::factory()->create([
        'company_id' => $this->company->id,
        'default_withholding_code_id' => $this->wc100,
    ]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

function postRentBill(): Bill
{
    return app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id,
        'vendor_id' => test()->landlord->id,
        'bill_date' => '2026-06-05',
        'pricing_mode' => 'vat_inclusive',
        'lines' => [[
            'description' => 'Office rent', 'qty' => '1', 'unit_price' => 56_000_00,
            'tax_code_id' => test()->vat12, 'vat_bucket' => VatBucket::Common->value,
            'expense_or_asset_account_id' => account(test()->company, '6100')->id,
        ]],
    ]));
}

it('pays a bill withholding 5% EWT on the VAT-exclusive base (VP-1 fixture)', function () {
    $bill = postRentBill();

    $payment = app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->landlord->id,
        'payment_date' => '2026-06-20',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $bill->id, 'amount' => 56_000_00]],
    ]));

    expect($payment->gross_applied->minor)->toBe(56_000_00)
        ->and($payment->ewt->minor)->toBe(2_500_00)        // 5% of 50,000 base
        ->and($payment->net_paid->minor)->toBe(53_500_00)
        ->and($payment->voucher_no)->toBe('PV-2026-000001')
        ->and($bill->fresh()->status)->toBe(InvoiceStatus::Paid);

    $period6 = $this->company->periods()->where('period_no', 6)->value('id');
    $ewtPayable = PeriodBalance::query()->where('account_id', account($this->company, '2210')->id)->where('period_id', $period6)->first();
    expect($ewtPayable->closing->minor)->toBe(-2_500_00); // credit balance

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('persists 2307 withholding transaction data', function () {
    $bill = postRentBill();

    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => $this->landlord->id,
        'payment_date' => '2026-06-20',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $bill->id, 'amount' => 56_000_00]],
    ]));

    $wt = WithholdingTransaction::query()->where('company_id', $this->company->id)->first();
    expect($wt)->not->toBeNull()
        ->and($wt->atc)->toBe('WC100')
        ->and($wt->base->minor)->toBe(50_000_00)
        ->and($wt->rate_bp)->toBe(500)
        ->and($wt->ewt->minor)->toBe(2_500_00)
        ->and($wt->vendor_id)->toBe($this->landlord->id);
});

<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

function postPosInvoice(int $unitPrice): Invoice
{
    return app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => test()->company->id,
        'customer_id' => test()->customer->id,
        'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => $unitPrice,
            'tax_code_id' => test()->vat12, 'income_account_id' => account(test()->company, '4200')->id,
        ]],
    ]));
}

it('rolls invoice status from posted to partially_paid to paid', function () {
    $invoice = postPosInvoice(112_000_00); // total 112,000

    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_date' => '2026-06-15',
        'deposit_to_account_id' => account($this->company, '1120')->id,
        'amount' => 40_000_00,
        'applications' => [['invoice_id' => $invoice->id, 'amount' => 40_000_00]],
    ]));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_date' => '2026-06-16',
        'deposit_to_account_id' => account($this->company, '1120')->id,
        'amount' => 72_000_00,
        'applications' => [['invoice_id' => $invoice->id, 'amount' => 72_000_00]],
    ]));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->outstanding())->toBe(0);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('records creditable withholding tax when the customer withholds', function () {
    $invoice = postPosInvoice(112_000_00);

    // Customer pays 109,500 cash and withholds 2,500 EWT (gross settled 112,000).
    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_date' => '2026-06-15',
        'deposit_to_account_id' => account($this->company, '1120')->id,
        'amount' => 109_500_00,
        'ewt_withheld' => 2_500_00,
        'applications' => [['invoice_id' => $invoice->id, 'amount' => 112_000_00]],
    ]));

    $period6 = $this->company->periods()->where('period_no', 6)->value('id');
    $cwt = PeriodBalance::query()->where('account_id', account($this->company, '1450')->id)->where('period_id', $period6)->first();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($cwt->closing->minor)->toBe(2_500_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('rejects an application exceeding the invoice outstanding', function () {
    $invoice = postPosInvoice(112_000_00);

    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_date' => '2026-06-15',
        'deposit_to_account_id' => account($this->company, '1120')->id,
        'amount' => 200_000_00,
        'applications' => [['invoice_id' => $invoice->id, 'amount' => 200_000_00]],
    ]));
})->throws(RuntimeException::class);

<?php

declare(strict_types=1);

use App\Actions\Receivables\ApplyCreditMemo;
use App\Actions\Receivables\PostCreditMemo;
use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\CreditMemoData;
use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

it('posts a credit memo that debits Output VAT and applies to an invoice', function () {
    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'invoice_date' => '2026-06-10',
        'lines' => [[
            'description' => 'POS', 'qty' => '2', 'unit_price' => 56_000_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    // Return 1 unit: ₱56,000 inclusive -> net 50,000 + VAT 6,000.
    $memo = app(PostCreditMemo::class)->handle(CreditMemoData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'memo_date' => '2026-06-20',
        'lines' => [[
            'description' => 'POS return', 'qty' => '1', 'unit_price' => 56_000_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    expect($memo->vat_amount->minor)->toBe(6_000_00)
        ->and($memo->total->minor)->toBe(56_000_00);

    app(ApplyCreditMemo::class)->handle($memo, [['invoice_id' => $invoice->id, 'amount' => 56_000_00]]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->fresh()->outstanding())->toBe(56_000_00);

    // Net Output VAT for the quarter = 12,000 (invoice) − 6,000 (memo) = 6,000 credit.
    $period6 = $this->company->periods()->where('period_no', 6)->value('id');
    $outputVat = PeriodBalance::query()->where('account_id', account($this->company, '2200')->id)
        ->where('period_id', $period6)->first();
    expect($outputVat->closing->minor)->toBe(-6_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

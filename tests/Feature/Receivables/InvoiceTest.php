<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Actions\Receivables\VoidInvoice;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'terms_days' => 30]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

function invoiceData(array $overrides = []): InvoiceData
{
    return InvoiceData::from(array_merge([
        'company_id' => test()->company->id,
        'customer_id' => test()->customer->id,
        'invoice_date' => '2026-06-10',
        'lines' => [],
    ], $overrides));
}

it('posts a mixed rice + POS invoice with segregated income and VAT', function () {
    $data = invoiceData([
        'lines' => [
            [
                'description' => 'Rice 25kg', 'qty' => '360', 'unit_price' => 2_500_00,
                'tax_code_id' => $this->exempt, 'income_account_id' => account($this->company, '4100')->id,
            ],
            [
                'description' => 'POS Terminal', 'qty' => '2', 'unit_price' => 56_000_00,
                'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
            ],
        ],
    ]);

    $invoice = app(PostInvoice::class)->handle($data);

    expect($invoice->status)->toBe(InvoiceStatus::Posted)
        ->and($invoice->number)->toBe('INV-2026-000001')
        ->and($invoice->exempt_sales->minor)->toBe(900_000_00)
        ->and($invoice->vatable_sales->minor)->toBe(100_000_00)
        ->and($invoice->vat_amount->minor)->toBe(12_000_00)
        ->and($invoice->total->minor)->toBe(1_012_000_00)
        ->and($invoice->due_date->toDateString())->toBe('2026-07-10');

    // Ledger: Dr AR 1,012,000 / Cr 4100 900,000 / Cr 4200 100,000 / Cr 2200 12,000.
    $je = $invoice->journalEntry;
    expect($je->status)->toBe(JournalStatus::Posted);

    $period6 = $this->company->periods()->where('period_no', 6)->value('id');
    $ar = PeriodBalance::query()->where('account_id', account($this->company, '1200')->id)->where('period_id', $period6)->first();
    $outputVat = PeriodBalance::query()->where('account_id', account($this->company, '2200')->id)->where('period_id', $period6)->first();
    expect($ar->closing->minor)->toBe(1_012_000_00)
        ->and($outputVat->closing->minor)->toBe(-12_000_00); // credit balance

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('cascades header dimensions to journal lines', function () {
    $dept = Department::factory()->create(['company_id' => $this->company->id]);

    $invoice = app(PostInvoice::class)->handle(invoiceData([
        'department_id' => $dept->id,
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    $incomeLine = $invoice->journalEntry->lines->firstWhere('account_id', account($this->company, '4200')->id);
    expect($incomeLine->department_id)->toBe($dept->id);
});

it('rejects an opening invoice from touching income — posts against OBE', function () {
    $invoice = app(PostInvoice::class)->handle(invoiceData([
        'is_opening' => true,
        'lines' => [[
            'description' => 'Opening balance', 'qty' => '1', 'unit_price' => 50_000_00,
            'tax_code_id' => $this->exempt, 'income_account_id' => account($this->company, '4100')->id,
        ]],
    ]));

    $accounts = $invoice->journalEntry->lines->pluck('account_id')->all();
    expect($accounts)->toContain(account($this->company, '3950')->id)
        ->and($accounts)->not->toContain(account($this->company, '4100')->id);
});

it('voids a posted invoice by reversing its entry', function () {
    $invoice = app(PostInvoice::class)->handle(invoiceData([
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    app(VoidInvoice::class)->handle($invoice, 'customer cancelled');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Voided)
        ->and($invoice->journalEntry->fresh()->status)->toBe(JournalStatus::Reversed);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('blocks voiding an invoice with payments applied', function () {
    $invoice = app(PostInvoice::class)->handle(invoiceData([
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    app(ReceiveCustomerPayment::class)->handle(
        CustomerPaymentData::from([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'payment_date' => '2026-06-15',
            'deposit_to_account_id' => account($this->company, '1120')->id,
            'amount' => 11_200_00,
            'applications' => [['invoice_id' => $invoice->id, 'amount' => 11_200_00]],
        ])
    );

    app(VoidInvoice::class)->handle($invoice->fresh(), 'too late');
})->throws(RuntimeException::class);

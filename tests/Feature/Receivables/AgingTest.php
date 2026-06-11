<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Services\Reports\ArAgingReport;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'terms_days' => 0]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

function agingInvoice(string $date): Invoice
{
    return app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => test()->company->id,
        'customer_id' => test()->customer->id,
        'invoice_date' => $date,
        'due_date' => $date, // due immediately (terms 0)
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => test()->vat12, 'income_account_id' => account(test()->company, '4200')->id,
        ]],
    ]));
}

it('buckets outstanding invoices by age', function () {
    agingInvoice('2026-06-25'); // 5 days past due as of 6-30 -> 1_30
    agingInvoice('2026-05-20'); // 41 days -> 31_60
    agingInvoice('2026-03-01'); // 121 days -> 90_plus

    $aging = app(ArAgingReport::class)->build($this->company->id, '2026-06-30');

    expect($aging['buckets']['1_30'])->toBe(11_200_00)
        ->and($aging['buckets']['31_60'])->toBe(11_200_00)
        ->and($aging['buckets']['90_plus'])->toBe(11_200_00)
        ->and($aging['buckets']['61_90'])->toBe(0)
        ->and($aging['total'])->toBe(33_600_00);
});

it('treats not-yet-due invoices as current', function () {
    $c = Customer::factory()->create(['company_id' => $this->company->id, 'terms_days' => 30]);
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => $c->id,
        'invoice_date' => '2026-06-28',
        'due_date' => '2026-07-28',
        'lines' => [[
            'description' => 'POS', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id,
        ]],
    ]));

    $aging = app(ArAgingReport::class)->build($this->company->id, '2026-06-30');
    expect($aging['buckets']['current'])->toBe(11_200_00);
});

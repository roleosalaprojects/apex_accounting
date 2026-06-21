<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Services\Fx\RecordForeignSettlement;

beforeEach(function () {
    $this->company = makeCompany();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->actor = makeUserWithRole($this->company, CompanyRole::Accountant);
    $this->bankGl = account($this->company, '1120'); // Cash in Bank
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

/** A USD invoice booked at ₱56,000 (face $1,000 @ issue rate). */
function foreignInvoice($test, float $issueRate = 56.0): Invoice
{
    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'invoice_date' => '2026-06-15',
        'pricing_mode' => 'vat_exclusive',
        'lines' => [[
            'description' => 'Export sale',
            'qty' => '1',
            'unit_price' => 56_000_00,
            'tax_code_id' => $test->exempt,
            'income_account_id' => account($test->company, '4100')->id,
        ]],
    ]), $test->actor);

    $invoice->update(['currency_code' => 'USD', 'exchange_rate' => $issueRate, 'foreign_total' => 1_000_00]);

    return $invoice->fresh();
}

it('records a realized FX gain when settled at a higher rate', function () {
    $invoice = foreignInvoice($this, 56.0);

    $r = app(RecordForeignSettlement::class)->handle($invoice, 58.0, $this->bankGl->id, '2026-06-20', $this->actor);

    expect($r['booked'])->toBe(56_000_00)
        ->and($r['cash'])->toBe(58_000_00)
        ->and($r['fx'])->toBe(2_000_00)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($r['fx_entry']->lines->firstWhere('account_id', account($this->company, '4950')->id)->credit->minor)->toBe(2_000_00);
});

it('records a realized FX loss when settled at a lower rate', function () {
    $invoice = foreignInvoice($this, 56.0);

    $r = app(RecordForeignSettlement::class)->handle($invoice, 54.0, $this->bankGl->id, '2026-06-20', $this->actor);

    expect($r['cash'])->toBe(54_000_00)
        ->and($r['fx'])->toBe(-2_000_00)
        ->and($r['fx_entry']->lines->firstWhere('account_id', account($this->company, '4950')->id)->debit->minor)->toBe(2_000_00);
});

it('rejects settling a PHP (non-foreign) invoice', function () {
    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'invoice_date' => '2026-06-15',
        'pricing_mode' => 'vat_exclusive',
        'lines' => [[
            'description' => 'Local sale',
            'qty' => '1',
            'unit_price' => 1000_00,
            'tax_code_id' => $this->exempt,
            'income_account_id' => account($this->company, '4100')->id,
        ]],
    ]), $this->actor);

    expect(fn () => app(RecordForeignSettlement::class)->handle($invoice->fresh(), 58.0, $this->bankGl->id, '2026-06-20', $this->actor))
        ->toThrow(RuntimeException::class);
});

<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\FxRevaluation;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\TaxCode;
use App\Services\Fx\FxRevaluationReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('reports unrealized FX gain on an open foreign receivable', function () {
    $company = makeCompany();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $actor = makeUserWithRole($company, CompanyRole::Accountant);
    $exempt = TaxCode::query()->where('company_id', $company->id)->where('code', 'EXEMPT')->value('id');
    ExchangeRate::factory()->create(['company_id' => $company->id, 'currency_code' => 'USD', 'rate_date' => '2026-06-30', 'rate' => 58]);

    $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_date' => '2026-06-15',
        'pricing_mode' => 'vat_exclusive',
        'lines' => [[
            'description' => 'Export sale',
            'qty' => '1',
            'unit_price' => 56_000_00,
            'tax_code_id' => $exempt,
            'income_account_id' => account($company, '4100')->id,
        ]],
    ]), $actor);
    $invoice->update(['currency_code' => 'USD', 'exchange_rate' => 56, 'foreign_total' => 1_000_00]);

    $r = app(FxRevaluationReport::class)->build($company->id, '2026-06-30');

    // $1,000 o/s × 58 = ₱58,000 revalued vs ₱56,000 booked → +₱2,000 unrealized gain
    expect($r['total_unrealized'])->toBe(2_000_00)
        ->and($r['rows'][0]['type'])->toBe('AR')
        ->and($r['rows'][0]['foreign_outstanding'])->toBe(1_000_00)
        ->and($r['rows'][0]['revalued'])->toBe(58_000_00);
});

it('renders the FX revaluation page', function () {
    $company = makeCompany();
    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);

    Livewire::test(FxRevaluation::class)->set('asOf', '2026-06-30')->assertOk();
});
